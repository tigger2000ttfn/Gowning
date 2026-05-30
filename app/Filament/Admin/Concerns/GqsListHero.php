<?php

namespace App\Filament\Admin\Concerns;

/**
 * Gives a resource List page the GQS page-hero header (matching custom pages),
 * while keeping the standard Filament table + header actions intact.
 *
 * Consuming List page sets public $gqsTitle / $gqsSubtitle / $gqsIcon.
 */
trait GqsListHero
{
    public function getView(): string { return 'filament.resource-list'; }

    public function getHeading(): string { return ''; }       // hide default heading (hero replaces it)
    public function getSubheading(): ?string { return null; }  // hero carries the subtitle
}
