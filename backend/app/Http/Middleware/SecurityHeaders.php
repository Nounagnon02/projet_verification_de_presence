<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Ajoute les en-têtes de sécurité HTTP (CSP, HSTS, X-Frame-Options, Permissions-Policy, etc.).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Content-Security-Policy - Policy stricte pour SPA React + API Laravel
        // 'unsafe-inline' pour scripts/styles requis par Vite en dev, à retirer en prod
        // 'unsafe-eval' requis par React DevTools et certaines libs
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        // En production, durcir le CSP (retirer unsafe-inline/unsafe-eval)
        if (app()->environment('production')) {
            $cspDirectives = [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ];
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        // HSTS (HTTP Strict Transport Security) - Force HTTPS pendant 1 an
        // preload = demander inclusion dans les listes HSTS des navigateurs
        // includeSubDomains = appliquer aux sous-domaines
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Anti-clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // MIME sniffing protection
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer policy - strict pour la confidentialité
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy (anciennement Feature-Policy) - Bloquer APIs sensibles
        // camera, microphone, geolocation non utilisés par l'app
        // payment, usb, bluetooth, etc. bloqués par défaut
        $response->headers->set('Permissions-Policy', implode(', ', [
            // Géolocalisation autorisée pour l'app mobile de scan (CDC 7.4.2)
            'geolocation=(self)',
            'camera=()',
            'microphone=()',
            'payment=()',
            'usb=()',
            'bluetooth=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'battery=()',
            'cross-origin-isolated=()',
            'display-capture=()',
            'document-domain=()',
            'encrypted-media=()',
            'execution-while-not-rendered=()',
            'execution-while-out-of-viewport=()',
            'fullscreen=()',
            'gamepad=()',
            'hid=()',
            'idle-detection=()',
            'local-fonts=()',
            'midi=()',
            'otp-credentials=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'screen-wake-lock=()',
            'serial=()',
            'speaker-selection=()',
            'sync-xhr=()',
            'trust-token=()',
            'web-share=()',
            'window-management=()',
            'xr-spatial-tracking=()',
        ]));

        // X-Permitted-Cross-Domain-Policies (pour Adobe Flash/PDF, legacy mais inoffensif)
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Cross-Origin-Embedder-Policy (COEP) - Exiger isolation cross-origin
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Cross-Origin-Opener-Policy (COOP) - Isoler le contexte de navigation
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Cross-Origin-Resource-Policy (CORP) - Protéger les ressources
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Cache-Control pour les réponses API (éviter mise en cache sensible)
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
