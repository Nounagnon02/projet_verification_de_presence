<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_settings');
    }
};
