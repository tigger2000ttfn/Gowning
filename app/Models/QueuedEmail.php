<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueuedEmail extends Model
{
    protected $fillable = ['to_email', 'to_name', 'subject', 'body', 'sent_at'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
}
