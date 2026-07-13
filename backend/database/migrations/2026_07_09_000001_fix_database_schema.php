<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Supprimer les tables orphelines (members, badges, rewards, member_badges, redemptions)
        // Ces tables ne sont pas utilisées dans le code métier principal (Etudiant n'a pas de relation Member)
        // Désactiver les FK checks pour contourner les contraintes (anomalies.member_id, member_badges.member_id, redemptions.member_id)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('redemptions');
        Schema::dropIfExists('member_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('members');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // 2. Ajouter les index manquants sur les tables critiques

        // Index sur anomalies.resolved + created_at pour les requêtes d'alertes non résolues
        Schema::table('anomalies', function (Blueprint $table) {
            $table->index(['resolved', 'created_at'], 'anomalies_resolved_created_index');
        });

        // Index sur notifications.read_at pour les requêtes de notifications non lues
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('read_at', 'notifications_read_at_index');
        });

        // Index sur qrcodes.expire_at + actif pour le nettoyage cron
        Schema::table('qrcodes', function (Blueprint $table) {
            $table->index(['expire_at', 'actif'], 'qrcodes_expire_actif_index');
        });

        // Index sur etudiant_ec.annee_id pour les requêtes par année
        Schema::table('etudiant_ec', function (Blueprint $table) {
            $table->index('annee_id', 'etudiant_ec_annee_id_index');
        });

        // Index sur chat_messages.read_at (si la colonne existe)
        if (Schema::hasColumn('chat_messages', 'read_at')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->index('read_at', 'chat_messages_read_at_index');
            });
        }

        // 3. Contraintes CHECK pour les champs de type statut
        // Note: Les contraintes CHECK nécessitent MySQL 8.0.16+/MariaDB 10.2.1+

        if (DB::connection()->getDriverName() === 'mysql') {
            if (Schema::hasColumn('evenements', 'statut')) {
                DB::statement("ALTER TABLE evenements ADD CONSTRAINT evenements_statut_check CHECK (statut IN ('planifie', 'en_cours', 'termine', 'annule'))");
            }

            if (Schema::hasColumn('presences', 'statut')) {
                DB::statement("ALTER TABLE presences ADD CONSTRAINT presences_statut_check CHECK (statut IN ('present', 'absent', 'retard', 'justifie'))");
            }

            if (Schema::hasColumn('support_tickets', 'status')) {
                DB::statement("ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_status_check CHECK (status IN ('open', 'in_progress', 'resolved', 'closed'))");
            }

            if (Schema::hasColumn('users', 'role')) {
                DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'faculte_admin', 'doyen', 'chef_departement', 'resp_pedagogique', 'scolarite', 'enseignant', 'etudiant'))");
            }
        }

        // NOTE: evenements.salle est conservée car elle est utilisée par les seeders
        // et le code métier. La colonne salle_id (FK) est la référence pour les nouvelles
        // fonctionnalités, mais l'ancienne colonne string reste pour compatibilité.

        // 5. Ajouter index sur users.etablissement_id + role pour les requêtes RBAC
        Schema::table('users', function (Blueprint $table) {
            $table->index(['etablissement_id', 'role'], 'users_etablissement_role_index');
        });

        // 6. Ajouter index composés sur presences pour les requêtes par date
        Schema::table('presences', function (Blueprint $table) {
            $table->index(['etudiant_id', 'heure_scan'], 'presences_etudiant_heure_index');
            $table->index(['evenement_id', 'heure_scan'], 'presences_evenement_heure_index');
        });
    }

    public function down(): void
    {
        // Rollback des index
        Schema::table('presences', function (Blueprint $table) {
            $table->dropIndex('presences_etudiant_heure_index');
            $table->dropIndex('presences_evenement_heure_index');
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropIndex('anomalies_resolved_created_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_read_at_index');
        });

        Schema::table('qrcodes', function (Blueprint $table) {
            $table->dropIndex('qrcodes_expire_actif_index');
        });

        Schema::table('etudiant_ec', function (Blueprint $table) {
            $table->dropIndex('etudiant_ec_annee_id_index');
        });

        if (Schema::hasColumn('chat_messages', 'read_at')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropIndex('chat_messages_read_at_index');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_etablissement_role_index');
        });

        // Rollback des contraintes CHECK (MySQL uniquement)
        if (DB::connection()->getDriverName() === 'mysql') {
            // D'abord désactiver FK checks pour les tables qui seront modifiées
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement("ALTER TABLE evenements DROP CHECK evenements_statut_check");
            DB::statement("ALTER TABLE presences DROP CHECK presences_statut_check");
            DB::statement("ALTER TABLE support_tickets DROP CHECK support_tickets_status_check");
            DB::statement("ALTER TABLE users DROP CHECK users_role_check");
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Recréer les tables orphelines si nécessaire (pour rollback complet)
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('group')->nullable();
            $table->foreignId('users_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('rgpd_consent')->default(false);
            $table->timestamp('rgpd_consent_at')->nullable();
            $table->string('consent_method')->nullable();
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('points_cost')->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('criteria')->nullable();
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        Schema::create('member_badges', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('badge_id')->constrained('badges')->onDelete('cascade');
            $table->timestamp('earned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->primary(['member_id', 'badge_id']);
        });

        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('reward_id')->constrained('rewards')->onDelete('cascade');
            $table->integer('points_spent');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
        });

        // Recréer la colonne salle si elle a été supprimée (MySQL uniquement)
        if (DB::connection()->getDriverName() !== 'sqlite' && !Schema::hasColumn('evenements', 'salle')) {
            Schema::table('evenements', function (Blueprint $table) {
                $table->string('salle')->nullable()->after('salle_id');
            });
        }
    }
};