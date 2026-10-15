<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Catalog;

/**
 * Kolomcatalogus van een grid: welke kolommen bestaan, waar ze vandaan komen en of ze
 * snel laadbaar zijn.
 *
 * Elk item: [
 *   'label' => string,          // weergavelabel
 *   'type'  => string,          // zie ValueFormatter
 *   'fast'  => bool,            // true = direct uit de gridtabel of via goedkope join
 *   'sort'  => bool,            // sorteerbaar
 *   'src'   => string,          // 'grid' | 'join' | 'pseudo' | 'plugin' (per rij berekend door de kolomklasse van het standaardgrid)
 *   'col'   => ?string,         // kolomnaam in de bron (voor src grid/join)
 *   'join'  => ?string,         // join-id (bv. 'delivery', 'customer', 'mailchimp', 'stock', 'eav:<code>')
 *   'reason'=> ?string,         // waarom niet snel laadbaar (bij fast=false)
 *   'impact'=> ?int,            // gemeten ms per 20 rijen (plugin-kolommen)
 * ]
 */
interface CatalogInterface
{
    public const GRID = '';

    /** @return array<string, array> sleutel => definitie (volgorde = standaardvolgorde) */
    public function available(): array;

    /** @return string[] standaardkolommen (door de beheerder opgeslagen, anders ingebouwd) */
    public function defaults(): array;

    /** Gekozen kolommen, in gekozen volgorde (ook langzame plugin-kolommen). @return array<string, array> */
    public function selected(): array;

    /** Naam van het standaard UI-component (voor labels/knoppen). */
    public function uiComponent(): string;
}
