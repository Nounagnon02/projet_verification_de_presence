<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Chiffre les secrets TOTP existants, écrits en clair avant que le modèle
 * User ne déclare le cast « encrypted » sur two_factor_secret et
 * two_factor_recovery_codes.
 *
 * Une lecture de la base — le scénario même qui a motivé la rotation de
 * APP_KEY après la fuite des secrets de déploiement — livrait jusqu'ici un
 * secret TOTP directement exploitable pour générer des codes valides.
 *
 * Passe par des requêtes SQL brutes, jamais par le modèle Eloquent : celui-ci
 * porte désormais le cast « encrypted » et tenterait de DÉCHIFFRER la valeur
 * en clair dès sa lecture, ce qui échouerait avant même d'atteindre ce code.
 *
 * Idempotente : chaque valeur est d'abord essayée au déchiffrement ; si elle
 * réussit, elle est déjà chiffrée et n'est pas retouchée.
 */
return new class extends Migration
{
    private const COLONNES = ['two_factor_secret', 'two_factor_recovery_codes'];

    public function up(): void
    {
        $this->chaqueLigne(function (array $utilisateur) {
            $misesAJour = [];

            foreach (self::COLONNES as $colonne) {
                $valeur = $utilisateur[$colonne];
                if ($valeur === null || $this->estDejaChiffree($valeur)) {
                    continue;
                }

                $misesAJour[$colonne] = Crypt::encryptString($valeur);
            }

            if ($misesAJour !== []) {
                DB::table('users')->where('id', $utilisateur['id'])->update($misesAJour);
            }
        });
    }

    /**
     * Non réversible en toute rigueur : déchiffrer redonnerait le texte en
     * clair, mais rien ne garantit qu'aucune valeur n'ait été chiffrée par
     * ailleurs entre-temps. Laisser tel quel plutôt que de risquer de
     * déchiffrer une valeur qui ne devrait plus jamais l'être.
     */
    public function down(): void
    {
    }

    private function chaqueLigne(callable $traiter): void
    {
        DB::table('users')
            ->select(['id', ...self::COLONNES])
            ->where(function ($q) {
                foreach (self::COLONNES as $colonne) {
                    $q->orWhereNotNull($colonne);
                }
            })
            ->orderBy('id')
            ->chunk(200, function ($utilisateurs) use ($traiter) {
                foreach ($utilisateurs as $utilisateur) {
                    $traiter((array) $utilisateur);
                }
            });
    }

    private function estDejaChiffree(string $valeur): bool
    {
        try {
            Crypt::decryptString($valeur);

            return true;
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return false;
        }
    }
};
