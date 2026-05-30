<?php

namespace App\Filament\Admin\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Gives a resource List page the GQS page-hero header (matching custom pages),
 * while keeping the standard Filament table + header actions intact.
 *
 * Consuming List page sets public $gqsTitle / $gqsSubtitle / $gqsIcon.
 */
trait GqsListHero
{
    public function getView(): string { return 'filament.resource-list'; }

    public function getHeading(): string { return ''; }
    public function getSubheading(): ?string { return null; }

    // Returning a (blank) view makes the page component take the truthy-header
    // branch and skip its default header+actions block, so they don't render twice.
    public function getHeader(): ?View
    {
        return view('filament.empty-header');
    }
}
