<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire la fonctionnalité de gamification (jamais exploitée côté application).
 *
 * Les tables sont supprimées avant leurs dépendances (redemptions et
 * member_badges avant members/rewards/badges, contraintes de clé étrangère).
 * down() les recrée à l'identique pour rester réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('member_badges');
        Schema::dropIfExists('redemptions');
        Schema::dropIfExists('alert_settings');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('members');
    }

    public function down(): void
    {
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
            $table->integer('cost');
            $table->string('image_url')->nullable();
            $table->integer('stock')->nullable()->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->string('condition');
            $table->integer('threshold')->default(0);
            $table->integer('points')->default(0);
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('alert_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('group');
            $table->boolean('is_active')->default(true);
            $table->boolean('absence_alerts_enabled')->default(true);
            $table->integer('alert_after_minutes')->default(30);
            $table->time('event_start_time')->default('09:00:00');
            $table->text('alert_message_template')->nullable();
            $table->boolean('reminders_enabled')->default(true);
            $table->integer('reminder_hours_before')->default(24);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->string('admin_phone')->nullable();
            $table->string('admin_email')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'group']);
        });

        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('reward_id')->constrained('rewards')->onDelete('cascade');
            $table->integer('points_spent');
            $table->timestamp('purchased_at')->nullable();
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
    }
};
