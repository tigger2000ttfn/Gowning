<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\GqsActivityLog;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use Auditable, GqsActivityLog;

    protected $fillable = ['key', 'name', 'subject', 'body_html', 'is_enabled'];
    protected function casts(): array { return ['is_enabled' => 'boolean']; }

    /** Render a template's subject+body with token replacements. */
    public static function render(string $key, array $tokens = []): ?array
    {
        $t = static::where('key', $key)->where('is_enabled', true)->first();
        if (! $t) return null;
        $map = [];
        foreach ($tokens as $k => $v) { $map['{' . $k . '}'] = (string) $v; }
        return [
            'subject' => strtr($t->subject, $map),
            'body_html' => strtr($t->body_html, $map),
        ];
    }
}
