<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectronicSignature extends Model
{
    protected $fillable = [
        'signable_type', 'signable_id', 'user_id',
        'signer_name', 'meaning', 'statement', 'signed_at',
    ];

    protected function casts(): array { return ['signed_at' => 'datetime']; }

    public function signable(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
