<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Code d'accès de l'étudiant : le secret qui manquait à sa connexion.
 *
 * L'application mobile authentifiait l'étudiant avec son email et son
 * identifiant unique. Or cet identifiant est déterministe — il vaut
 * NOM_PRENOM_MATRICULE_CODEFILIERE_ANNEE (IdentifiantService::generate) — et
 * l'email suit la convention de l'université : n'importe quel camarade de
 * promotion pouvait donc reconstituer les deux et pointer à la place d'un
 * absent. Rien dans ce couple n'était secret.
 *
 * La colonne accueille le HACHAGE d'un code à six chiffres, jamais le code
 * lui-même : une lecture de la base ne doit pas permettre de se connecter.
 *
 * Nullable, et il faut qu'elle le soit : les étudiants déjà inscrits n'ont pas
 * encore de code. La commande « etudiants:codes-acces --envoyer » le leur
 * attribue et le leur envoie ; d'ici là, leur connexion est refusée par un 409
 * explicite plutôt que par un « identifiants invalides » trompeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->string('code_acces')->nullable()->after('identifiant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropColumn('code_acces');
        });
    }
};
