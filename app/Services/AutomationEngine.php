<?php

namespace App\Services;

use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Enums\Capability;
use App\Models\Announcement;
use App\Models\AutomationRule;
use App\Models\Personnel;
use App\Models\Qualification;

/**
 * Fires user-configured automation rules (Monday.com style "when X, do Y").
 * Workflow code calls AutomationEngine::fire(trigger, context) at key moments;
 * every enabled rule matching that trigger runs its action.
 */
class AutomationEngine
{
    /**
     * @param AutomationTrigger $trigger
     * @param array $ctx  Context: ['personnel' => Personnel, 'qualification' => Qualification,
     *                                'stage' => string, 'message' => string]
     */
    public static function fire(AutomationTrigger $trigger, array $ctx = []): void
    {
        $rules = AutomationRule::where('is_enabled', true)
            ->where('trigger', $trigger->value)
            ->get();

        foreach ($rules as $rule) {
            // stage-specific gate
            if ($trigger === AutomationTrigger::StageChanged
                && $rule->trigger_stage
                && ($ctx['stage'] ?? null) !== $rule->trigger_stage) {
                continue;
            }

            try {
                static::runAction($rule, $ctx);
                $rule->increment('run_count');
                $rule->forceFill(['last_fired_at' => now()])->saveQuietly();
            } catch (\Throwable $e) {
                report($e); // never let an automation failure break the workflow
            }
        }
    }

    protected static function runAction(AutomationRule $rule, array $ctx): void
    {
        $action = AutomationAction::tryFrom($rule->action);
        $cfg = $rule->action_config ?? [];
        $person = $ctx['personnel'] ?? ($ctx['qualification']->personnel ?? null);
        $name = $person?->full_name ?? 'A team member';

        $title = $cfg['title'] ?? $rule->name;
        $body = strtr($cfg['message'] ?? '', [
            '{name}' => $name,
            '{employee_id}' => $person?->employee_id ?? '',
            '{stage}' => $ctx['stage'] ?? '',
        ]);
        if ($body === '') {
            $body = $rule->name . ($person ? " ({$name})" : '');
        }

        // If the rule references an email template, render it (subject + HTML body) with
        // the available tokens. A disabled/missing template sends nothing (respects the
        // template's on/off toggle). Rules without a template_key behave exactly as before.
        if (! empty($cfg['template_key'])) {
            $q = $ctx['qualification'] ?? null;
            $tokens = [
                'name' => $name,
                'employee_id' => $person?->employee_id ?? '',
                'stage' => $ctx['stage'] ?? '',
                'date' => $ctx['date'] ?? '',
                'time' => $ctx['time'] ?? '',
                'due_date' => $q && $q->due_date ? $q->due_date->format('d M Y') : ($ctx['due_date'] ?? ''),
                'class' => $ctx['class'] ?? '',
                'trainer' => $ctx['trainer'] ?? '',
                'result' => $ctx['result'] ?? '',
                'worklist' => $ctx['worklist'] ?? '',
                'veeva' => $ctx['veeva'] ?? '',
                'nc_number' => $ctx['nc_number'] ?? '',
                'reset_link' => $ctx['reset_link'] ?? '',
            ];
            $rendered = \App\Models\EmailTemplate::render($cfg['template_key'], $tokens);
            if ($rendered === null) {
                return; // template disabled or missing -> respect the toggle, send nothing
            }
            $title = $rendered['subject'];
            $body = $rendered['body_html'];
        }

        match ($action) {
            AutomationAction::NotifyCapability => self::notifyCapability($cfg, $title, $body),
            AutomationAction::NotifyPerson     => app(Notifier::class)->toPersonnel($person, $title, $body),
            AutomationAction::PostAnnouncement => Announcement::create([
                'title' => $title, 'body' => $body, 'author_name' => 'Automation', 'is_active' => true,
            ]),
            AutomationAction::QueueEmail => $person ? \App\Models\QueuedEmail::create([
                'to_email' => $person->email, 'to_name' => $person->full_name,
                'subject' => $title, 'body' => $body,
            ]) : null,
            default => null,
        };
    }

    protected static function notifyCapability(array $cfg, string $title, string $body): void
    {
        $cap = isset($cfg['capability']) ? Capability::tryFrom($cfg['capability']) : null;
        if ($cap) {
            Notifier::toCapability($cap, $title, $body);
        }
    }
}
