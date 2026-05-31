<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class VeevaDocument extends Model
{
    use Auditable;

    protected $fillable = [
        'doc_number', 'doc_id', 'vault_id', 'url', 'title', 'type', 'status', 'version', 'catalog_synced_at',
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
        if (preg_match('#doc_info/(\d+)#', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * The configured Vault base, e.g. https://astellas-quality-production.veevavault.com
     * (no trailing slash). Set in Settings as 'veeva_base_url'.
     */
    public static function baseUrl(): string
    {
        $base = trim((string) \App\Models\Setting::get('veeva_base_url', 'https://astellas-quality-production.veevavault.com'));
        return rtrim($base, '/');
    }

    /**
     * Build a document URL from a Vault document ID (the numeric internal id, e.g. 1112135) using the
     * documented doc_info URL scheme. Veeva exports give this numeric id in a plain column, so we can
     * construct the link without parsing a hyperlink out of the spreadsheet.
     */
    public static function urlFromVaultId(?string $vaultId): ?string
    {
        $vaultId = trim((string) $vaultId);
        if ($vaultId === '' || ! preg_match('/^\d+$/', $vaultId)) return null;
        return self::baseUrl() . '/ui/#doc_info/' . $vaultId;
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
