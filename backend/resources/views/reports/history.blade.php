<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 5px; color: #1E40AF; }
        .sous-titre { text-align: center; color: #555; margin-bottom: 20px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9px; }
        th, td { border: 1px solid #333; padding: 4px 5px; text-align: left; }
        th { background: #1E40AF; color: #fff; font-size: 9px; font-weight: bold; text-align: center; }
        tr:nth-child(even) td { background: #f3f4f6; }
        .statut-valide   { color: #155724; }
        .statut-suspect  { color: #856404; }
        .statut-absent   { color: #721c24; }
        .statut-rejete   { color: #721c24; }
        .qui { color: #555; font-size: 8px; }
        /* Critères de l'export : sans eux, la liste d'une filière pouvait
           passer pour l'historique complet. */
        table.criteres { width: auto; margin: 0 auto 12px; font-size: 9px; }
        table.criteres th { background: #eef2ff; color: #1E40AF; text-align: left; font-weight: bold; }
        table.criteres th, table.criteres td { border: 1px solid #c7d2fe; padding: 3px 8px; }
        table.criteres tr:nth-child(even) td { background: transparent; }
        .statut-en_retard { color: #856404; }
        .footer { text-align: center; margin-top: 25px; font-size: 8px; color: #888; }
        /* En-tête de marque : le logo est chargé depuis le disque via public_path(),
           dompdf n'ayant pas accès au réseau. */
        .marque { text-align: center; margin-bottom: 4px; }
        .marque img { height: 34px; }
        .total { margin-top: 15px; font-size: 11px; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <div class="marque">
        <img src="{{ public_path('images/logo-export.png') }}" alt="UAC Présences">
    </div>
    <h1>{{ $title }}</h1>
    <p class="sous-titre">Généré le {{ $date }}</p>

    <table class="criteres">
        <tr><th>Entité</th><td>{{ $contexte['criteres']['entite'] }}</td></tr>
        @if ($contexte['criteres']['semestres'])
            <tr><th>Semestre(s)</th><td>{{ $contexte['criteres']['semestres'] }}</td></tr>
        @endif
        @foreach ($contexte['criteres']['lignes'] as $critere)
            <tr><th>{{ $critere[0] }}</th><td>{{ $critere[1] }}</td></tr>
        @endforeach
        @unless ($contexte['criteres']['filtre'])
            <tr><th>Filtres</th><td>Aucun filtre : toutes les présences de l'entité</td></tr>
        @endunless
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Étudiant</th>
                <th>Matricule</th>
                <th>Filière</th>
                <th>Cours</th>
                <th>Date</th>
                <th>Heure</th>
                <th>Statut</th>
                <th>Origine</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($presences as $index => $p)
                <tr>
                    <td style="text-align:center;">{{ $index + 1 }}</td>
                    <td>{{ $p->etudiant->prenom ?? '' }} {{ $p->etudiant->nom ?? '' }}</td>
                    <td>{{ $p->etudiant->matricule ?? 'N/A' }}</td>
                    <td>{{ $p->etudiant->filiere?->code ?? 'N/A' }}</td>
                    <td>{{ $p->evenement->ec?->intitule ?? 'N/A' }}</td>
                    <td style="text-align:center;">{{ $p->evenement->date?->format('d/m/Y') ?? 'N/A' }}</td>
                    <td style="text-align:center;">{{ $p->heure_scan?->format('H:i') ?? 'N/A' }}</td>
                    <td style="text-align:center;">
                        <span class="statut-{{ $p->statut }}">
                            {{ $libellesStatut[$p->statut] ?? $p->statut }}
                        </span>
                    </td>
                    <td>
                        {{ $origines[$p->id]['libelle'] ?? 'Scan' }}
                        @if (!empty($origines[$p->id]['decide_par']))
                            <br><span class="qui">par {{ $origines[$p->id]['decide_par'] }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;">Aucune présence enregistrée.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="total">Total : {{ $total }} présence(s)</p>

    <div class="footer">
        Rapport généré le {{ $date }} &mdash; Système de Gestion de Présence
    </div>
</body>
</html>
