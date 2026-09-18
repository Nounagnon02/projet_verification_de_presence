<?php

declare(strict_types=1);

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Construit l'URL de validation d'une présence et son image QR Code.
 *
 * L'image est produite ici, sur le serveur, et non par un service tiers : le
 * token d'un QR Code déverrouille la prise de présence d'un cours, il ne doit
 * pas transiter vers un domaine externe pour être simplement transformé en
 * image.
 *
 * Le format retenu est le SVG et non le PNG : la génération PNG de
 * simple-qrcode exige l'extension Imagick, absente de l'image Docker de
 * production (qui embarque GD). Le SVG ne dépend d'aucune extension, reste net
 * à toute taille, et se rend aussi bien dans un navigateur que dans
 * l'application mobile via react-native-svg.
 */
class QrCodeImageService
{
    /**
     * URL vers laquelle pointe le QR Code : la page de validation du frontend.
     */
    public function urlValidation(string $token): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base . '/attendance/validate?token=' . urlencode($token);
    }

    /**
     * Image SVG du QR Code encodant l'URL de validation.
     */
    public function svg(string $token, int $taille = 300): string
    {
        return (string) QrCode::format('svg')
            ->size($taille)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($this->urlValidation($token));
    }
}
