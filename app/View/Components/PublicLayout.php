<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Coquille des pages publiques (accueil, démonstration).
 *
 * Contrairement à AppLayout, elle n'affiche pas la barre latérale et ne
 * suppose donc aucun utilisateur connecté : /demo utilisait AppLayout et
 * provoquait une erreur 500 pour les visiteurs.
 */
class PublicLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
    ) {
    }

    public function render(): View
    {
        return view('layouts.public');
    }
}
