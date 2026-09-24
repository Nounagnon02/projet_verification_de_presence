<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Carte de présence') }} - {{ $member->name }}</title>
    <style>
        body {
            /* DejaVu Sans et non Arial : DomPDF fait correspondre Arial à
               Helvetica, qui n'a ni ɖ ɛ ɔ (fon) ni ẹ ọ ṣ (yoruba). */
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 30px;
            color: #333;
        }
        .card {
            width: 340px;
            border: 2px solid #2563eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .card h1 {
            font-size: 16px;
            color: #2563eb;
            margin: 0 0 4px 0;
        }
        .card .subtitle {
            font-size: 11px;
            color: #666;
            margin-bottom: 16px;
        }
        .qr {
            margin: 10px auto;
        }
        .name {
            font-size: 18px;
            font-weight: bold;
            margin-top: 14px;
        }
        .groups {
            font-size: 12px;
            color: #555;
            margin-top: 4px;
        }
        .phone {
            font-size: 11px;
            color: #888;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Système de vérification de présence') }}</h1>
        <div class="subtitle">{{ __('Carte de présence personnelle') }}</div>
        <div class="qr"><img src="{{ $qrImageBase64 }}" width="200" height="200" alt="QR"></div>
        <div class="name">{{ $member->name }}</div>
        <div class="groups">{{ $member->groups->pluck('name')->implode(', ') }}</div>
        <div class="phone">{{ $member->phone }}</div>
    </div>
</body>
</html>
