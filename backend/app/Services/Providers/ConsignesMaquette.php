<?php

namespace App\Services\Providers;

/**
 * Consignes d'extraction d'une maquette pédagogique, communes à tous les
 * fournisseurs d'IA.
 *
 * Chaque fournisseur portait sa propre consigne, et toutes demandaient un
 * « volume_horaire » par EC. Sur la maquette réelle de l'IFRI (colonnes Cours,
 * TP/TD, SP, TPE, CTT, crédits), l'IA y mettait le CTT : 125 h pour un EC de
 * 5 crédits, dont 75 h de travail personnel que personne ne planifie. L'EC ne
 * pouvait jamais se terminer. Seules comptent les heures en présentiel, par
 * type ; le TPE et le CTT sont gardés pour information.
 */
final class ConsignesMaquette
{
    public static function cours(): string
    {
        return implode("\n", [
            "Tu analyses une maquette pédagogique (offre de formation) de l'Université d'Abomey-Calavi.",
            'Extrais TOUTES les Unités d\'Enseignement (UE) et leurs Éléments Constitutifs (EC).',
            '',
            'Réponds avec un JSON de la forme {"ues": [ ... ]}.',
            'Chaque UE : code, intitule, semestre (un numéro : « S3 » donne 3), credits (colonne « Crédits » ou « CECT », un nombre), et un tableau ecs.',
            'Chaque EC : code, intitule, puis ses heures EN PRÉSENTIEL par type, en nombres :',
            '- cm : colonne « Cours » ou « CM » ;',
            '- td : colonne « TD », quand elle est seule ;',
            '- tp : colonne « TP », quand elle est seule ;',
            '- td_tp : colonne « TP/TD » ou « TD/TP », quand la maquette regroupe les deux en une colonne.',
            'Pour information seulement, en champs séparés : sp (colonne « SP »), tpe (travail personnel de l\'étudiant), ctt (charge de travail totale).',
            '',
            'Règles :',
            '1. Ne mets JAMAIS le TPE ni le CTT dans cm, td, tp ou td_tp : ce ne sont pas des heures de cours.',
            '2. Une colonne absente ou vide vaut 0.',
            '3. Un EC dont la ligne ne donne que des crédits garde cm, td, tp et td_tp à 0.',
            '',
            'Réponds UNIQUEMENT avec le JSON valide.',
        ]);
    }
}
