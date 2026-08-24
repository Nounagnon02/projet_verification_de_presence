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
        .footer { text-align: center; margin-top: 25px; font-size: 8px; color: #888; }
        .marque { text-align: center; margin-bottom: 4px; }
        .marque img { height: 34px; }
        /* Bandeau d'indicateurs : dompdf ne gere pas flexbox, d'ou le tableau. */
        .kpi { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .kpi td { border: 1px solid #d1d5db; padding: 8px; text-align: center; width: 25%; }
        .kpi .valeur { font-size: 15px; font-weight: bold; color: #1E40AF; display: block; }
        .kpi .libelle { font-size: 8px; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <div class="marque">
        <img src="{{ public_path('images/logo-export.png') }}" alt="UAC Présences">
    </div>
    <h1>{{ $title }}</h1>
    <p class="sous-titre">
        {{ $filiere['code'] }} &mdash; {{ $filiere['intitule'] }} &middot; Généré le {{ $date }}
    </p>

    <table class="kpi">
        <tr>
            <td><span class="valeur">{{ $total_etudiants }}</span><span class="libelle">Étudiants</span></td>
            <td><span class="valeur">{{ $total_evenements }}</span><span class="libelle">Séances passées</span></td>
            <td><span class="valeur">{{ $total_presences }}</span><span class="libelle">Présences</span></td>
            <td><span class="valeur">{{ $taux_presence }}%</span><span class="libelle">Taux de présence</span></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Cours</th>
                <th>Date</th>
                <th>Présences</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($presences_par_cours as $index => $ligne)
                <tr>
                    <td style="text-align:center;">{{ $index + 1 }}</td>
                    <td>{{ $ligne['cours'] }}</td>
                    <td style="text-align:center;">{{ $ligne['date'] ?? 'N/A' }}</td>
                    <td style="text-align:center;">{{ $ligne['presences_count'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;">Aucune séance passée pour cette filière.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Rapport généré le {{ $date }} &mdash; Système de Gestion de Présence
    </div>
</body>
</html>
