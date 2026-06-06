<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { text-align: center; font-size: 18px; margin-bottom: 5px; }
        .sous-titre { text-align: center; color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
        th { background: #e0e0e0; font-size: 11px; }
        .statut-valide   { color: #155724; background: #d4edda; padding: 2px 6px; border-radius: 3px; }
        .statut-suspect  { color: #856404; background: #fff3cd; padding: 2px 6px; border-radius: 3px; }
        .statut-rejete   { color: #721c24; background: #f8d7da; padding: 2px 6px; border-radius: 3px; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="sous-titre">
        Date : {{ $date }} &mdash;
        Salle : {{ $evenement->salle ?? 'N/A' }} &mdash;
        Période : {{ \Carbon\Carbon::parse($evenement->heure_debut)->format('H:i') }} - {{ \Carbon\Carbon::parse($evenement->heure_fin)->format('H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Matricule</th>
                <th>Heure de scan</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($evenement->presences as $index => $p)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $p->etudiant->nom }}</td>
                    <td>{{ $p->etudiant->prenom }}</td>
                    <td>{{ $p->etudiant->matricule }}</td>
                    <td>{{ $p->heure_scan->format('H:i:s') }}</td>
                    <td><span class="statut-{{ $p->statut }}">{{ ucfirst($p->statut) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;">Aucune présence enregistrée.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p><strong>Total :</strong> {{ $evenement->presences->count() }} présence(s)</p>

    <div class="footer">
        Rapport généré le {{ $date }} &mdash; Système de Gestion de Présence
    </div>
</body>
</html>
