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
        .statut-en_retard { color: #856404; }
        .footer { text-align: center; margin-top: 25px; font-size: 8px; color: #888; }
        .total { margin-top: 15px; font-size: 11px; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="sous-titre">Généré le {{ $date }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Étudiant</th>
                <th>Matricule</th>
                <th>Filière</th>
                <th>Cours</th>
                <th>Date</th>
                <th>Heure Scan</th>
                <th>Statut</th>
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
                    <td style="text-align:center;">{{ $p->evenement->date?->format('Y-m-d') ?? 'N/A' }}</td>
                    <td style="text-align:center;">{{ $p->heure_scan?->format('H:i:s') ?? 'N/A' }}</td>
                    <td style="text-align:center;">
                        <span class="statut-{{ $p->statut }}">
                            @switch($p->statut)
                                @case('valide') Présent @break
                                @case('absent') Absent @break
                                @case('suspect') Suspect @break
                                @case('en_retard') En retard @break
                                @default {{ $p->statut }}
                            @endswitch
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;">Aucune présence enregistrée.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="total">Total : {{ $total }} présence(s)</p>

    <div class="footer">
        Rapport généré le {{ $date }} &mdash; Système de Gestion de Présence
    </div>
</body>
</html>
