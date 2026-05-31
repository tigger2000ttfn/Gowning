<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class NcDocument extends Model
{
    use Auditable;

    protected $fillable = [
        'nc_number', 'record_id', 'url', 'workflow_status', 'created_date', 'date_closed',
        'qa_approver', 'department', 'reference_numbers', 'site', 'sub_group', 'catalog_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'created_date' => 'date',
            'date_closed' => 'date',
            'catalog_synced_at' => 'datetime',
        ];
    }

    /** Statuses that mean the NC is finished (case-insensitive). */
    public const CLOSED_STATUSES = ['closed', 'canceled', 'cancelled', 'void', 'voided'];

    /**
     * The configured TrackWise (Salesforce) base, e.g. https://astellastwd.lightning.force.com
     * (no trailing slash). Stored in Settings as 'trackwise_base_url'.
     */
    public static function baseUrl(): string
    {
        $base = trim((string) \App\Models\Setting::get('trackwise_base_url', 'https://astellastwd.lightning.force.com'));
        return rtrim($base, '/');
    }

    /** Pull the Salesforce record id out of a Lightning record URL, if present. */
    public static function extractRecordId(?string $url): ?string
    {
        if (! $url) return null;
        if (preg_match('#/lightning/r/(?:[A-Za-z0-9_]+/)?([A-Za-z0-9]{15,18})#', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Build a Lightning record URL from a Salesforce record id (15 or 18 char). */
    public static function urlFromRecordId(?string $recordId): ?string
    {
        $recordId = trim((string) $recordId);
        if ($recordId === '' || ! preg_match('/^[A-Za-z0-9]{15,18}$/', $recordId)) return null;
        return self::baseUrl() . '/lightning/r/' . $recordId . '/view';
    }

    public static function findByNumber(?string $number): ?self
    {
        $number = trim((string) $number);
        if ($number === '') return null;
        return static::where('nc_number', $number)->first();
    }

    /** Best link for an NC number from the catalog. */
    public static function urlForNumber(?string $number): ?string
    {
        return static::findByNumber($number)?->url ?: null;
    }

    /** Is this NC closed/canceled per the catalog status? */
    public static function isClosed(?string $number): bool
    {
        $doc = static::findByNumber($number);
        if (! $doc) return false;
        return in_array(strtolower(trim((string) $doc->workflow_status)), self::CLOSED_STATUSES, true);
    }
}
