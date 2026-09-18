<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * « two_factor_secret » était en varchar(255) : suffisant pour le secret TOTP
 * en clair (32 caractères), mais pas pour sa forme chiffrée — l'enveloppe
 * JSON base64 d'AES-256-CBC (IV, empreinte, étiquette) dépasse 255 caractères
 * à elle seule. La migration suivante, qui chiffre les secrets existants,
 * échouait donc par troncature avant même d'avoir pu tourner.
 *
 * SQL brut plutôt que Blueprint::change() : ce dernier exige doctrine/dbal,
 * absent de ce projet (composer.lock ne le liste qu'en dépendance d'un tiers,
 * jamais installé dans vendor/).
 *
 * Doit s'exécuter AVANT 2026_09_18_110000_chiffrer_secrets_totp_existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN two_factor_secret TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN two_factor_secret TYPE varchar(255)');
    }
};
