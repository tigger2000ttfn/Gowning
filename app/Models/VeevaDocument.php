<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class VeevaDocument extends Model
{
    use Auditable;

    protected $fillable = [
        'doc_number', 'doc_id', 'url', 'title', 'type', 'status', 'version', 'catalog_synced_at',
    ];

    protected function casts(): array
    {
        return ['catalog_synced_at' => 'datetime'];
    }

    /** Pull the permalink surrogate id (V0Z...) out of a Veeva permalink URL, if present. */
    public static function extractDocId(?string $url): ?string
    {
        if (! $url) return null;
        if (preg_match('/permalink=([A-Za-z0-9]+)/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Find a catalog entry by its (normalized) doc number. */
    public static function findByNumber(?string $number): ?self
    {
        $number = trim((string) $number);
        if ($number === '') return null;
        return static::where('doc_number', $number)->first();
    }

    /** The best link for a given doc number from the catalog (full URL preferred). */
    public static function urlForNumber(?string $number): ?string
    {
        $doc = static::findByNumber($number);
        return $doc?->url ?: null;
    }
}
