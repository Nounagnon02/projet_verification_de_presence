<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Statistiques de présence') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #2563eb;
        }
        .info {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .stats {
            width: 100%;
            margin-bottom: 30px;
        }
        .stat-box {
            display: inline-block;
            text-align: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 30%;
            margin-right: 3%;
            vertical-align: top;
        }
        .stat-box:last-child {
            margin-right: 0;
        }
        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        .stat-box .number {
            font-size: 24px;
            font-weight: bold;
        }
        .present { color: #2563eb; }
        .total { color: #16a34a; }
        .rate { color: #7c3aed; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .no-data {
            text-align: center;
            font-style: italic;
            color: #666;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('Statistiques de présence') }}</h1>
        <p>{{ __('Groupe(s) :') }} {{ auth()->user()->groupsLed->pluck('name')->implode(', ') }}</p>
    </div>

    <div class="info">
        <p><strong>{{ __('Date :') }}</strong> {{ \Carbon\Carbon::parse($date)->translatedFormat(__('date.short')) }}</p>
        @if($search)
            <p><strong>{{ __('Recherche :') }}</strong> {{ $search }}</p>
        @endif
        <p><strong>{{ __('Généré le :') }}</strong> {{ now()->translatedFormat(__('date.datetime')) }}</p>
    </div>

    <div class="stats">
        <div class="stat-box">
            <h3>{{ __('Présents') }}</h3>
            <div class="number present">{{ $totalPresent }}</div>
        </div>
        <div class="stat-box">
            <h3>{{ __('Total membres') }}</h3>
            <div class="number total">{{ $totalMembres }}</div>
        </div>
        <div class="stat-box">
            <h3>{{ __('Taux de présence') }}</h3>
            <div class="number rate">{{ $tauxPresence }}%</div>
        </div>
    </div>

    <h2>{{ __('Liste des présences') }}</h2>
    
    @if($presences->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>{{ __('Nom') }}</th>
                    <th>{{ __('Téléphone') }}</th>
                    <th>{{ __('Heure d\'arrivée') }}</th>
                    <th>{{ __('Date') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($presences as $presence)
                    <tr>
                        <td>{{ $presence->member->name }}</td>
                        <td>{{ $presence->member->phone }}</td>
                        <td>{{ \Carbon\Carbon::parse($presence->time)->translatedFormat(__('date.time')) }}</td>
                        <td>{{ \Carbon\Carbon::parse($presence->date)->translatedFormat(__('date.short')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">
            <p>{{ __('Aucune présence enregistrée pour cette date.') }}</p>
        </div>
    @endif

    <div class="footer">
        <p>{{ __('Document généré automatiquement par le système de vérification de présence') }}</p>
    </div>
</body>
</html>