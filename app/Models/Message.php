<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'subject', 'body', 'read_at', 'parent_id'];
    protected function casts(): array { return ['read_at' => 'datetime']; }

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }

    public function scopeInbox($q, int $userId) { return $q->where('recipient_id', $userId); }
    public function scopeUnread($q) { return $q->whereNull('read_at'); }
}
