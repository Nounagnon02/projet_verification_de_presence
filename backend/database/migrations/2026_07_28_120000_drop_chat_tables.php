<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire la fonctionnalité de chat/messagerie support.
 *
 * Les tables sont supprimées (messages avant conversations, contrainte de clé
 * étrangère). down() les recrée à l'identique pour rester réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }

    public function down(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->string('department')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }
};
