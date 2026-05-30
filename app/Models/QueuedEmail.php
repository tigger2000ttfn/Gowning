<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueuedEmail extends Model
{
    protected $fillable = ['to_email', 'to_name', 'subject', 'body', 'body_html', 'template_key', 'sent_at', 'ics', 'ics_filename'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
}
