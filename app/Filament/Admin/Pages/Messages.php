<?php

namespace App\Filament\Admin\Pages;

use App\Models\Message;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Messages extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Messages';
    protected static ?string $title = 'Messages';
    protected static ?int $navigationSort = 1;
    public function getHeading(): string { return ''; }

    protected string $view = 'filament.pages.messages';

    public string $tab = 'inbox';          // inbox | sent | compose
    public ?int $openMessageId = null;

    // compose fields
    public ?int $toUserId = null;
    public string $subject = '';
    public string $body = '';
    public ?int $replyTo = null;

    public static function shouldRegisterNavigation(): bool { return true; }

    public function inbox()
    {
        return Message::with('sender')->inbox(Auth::id())->latest()->limit(100)->get();
    }

    public function sent()
    {
        return Message::with('recipient')->where('sender_id', Auth::id())->latest()->limit(100)->get();
    }

    public function unreadCount(): int
    {
        return Message::inbox(Auth::id())->unread()->count();
    }

    public function recipientOptions(): array
    {
        return User::where('is_active', true)->where('id', '!=', Auth::id())
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function open(int $id): void
    {
        $m = Message::find($id);
        if ($m && $m->recipient_id === Auth::id() && ! $m->read_at) {
            $m->forceFill(['read_at' => now()])->save();
        }
        $this->openMessageId = $id;
    }

    public function openMessage(): ?Message
    {
        return $this->openMessageId ? Message::with('sender', 'recipient')->find($this->openMessageId) : null;
    }

    public function startReply(int $toUserId, ?string $subject, int $parentId): void
    {
        $this->tab = 'compose';
        $this->toUserId = $toUserId;
        $this->subject = $subject && ! str_starts_with($subject, 'Re:') ? 'Re: ' . $subject : ($subject ?? '');
        $this->replyTo = $parentId;
        $this->openMessageId = null;
    }

    public function send(): void
    {
        $data = ['toUserId' => $this->toUserId, 'body' => $this->body];
        if (! $this->toUserId || trim($this->body) === '') {
            \Filament\Notifications\Notification::make()->danger()->title('Pick a recipient and write a message')->send();
            return;
        }

        $m = Message::create([
            'sender_id' => Auth::id(),
            'recipient_id' => $this->toUserId,
            'subject' => $this->subject ?: null,
            'body' => $this->body,
            'parent_id' => $this->replyTo,
        ]);

        // in-app notification to the recipient
        $recipient = User::find($this->toUserId);
        if ($recipient && \App\Models\NotificationPreference::wants($recipient->id, \App\Enums\NotificationEvent::NewMessage, 'in_app')) {
            \Filament\Notifications\Notification::make()
                ->title('New message from ' . (Auth::user()?->name ?? 'a colleague'))
                ->body(\Illuminate\Support\Str::limit($this->body, 80))
                ->icon('heroicon-o-chat-bubble-left-right')->info()
                ->sendToDatabase($recipient);
        }

        $this->reset(['toUserId', 'subject', 'body', 'replyTo']);
        $this->tab = 'sent';
        \Filament\Notifications\Notification::make()->success()->title('Message sent')->send();
    }
}
