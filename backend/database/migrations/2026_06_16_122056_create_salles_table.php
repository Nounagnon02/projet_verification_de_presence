<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->onDelete('cascade');
            $table->string('nom');           // ex: "Salle 101", "Amphi A"
            $table->string('code')->unique(); // ex: "S101", "AMPHI-A"
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('rayon_geofence_m')->default(50); // rayon GPS acceptable
            $table->string('ssid_attendu')->nullable();  // ex: "IFRI-WiFi"
            $table->string('bssid_attendu')->nullable(); // ex: "00:11:22:33:44:55"
            $table->string('ip_range')->nullable();      // ex: "192.168.1.0/24"
            $table->boolean('hors_reseau')->default(false); // si vrai, pas de vérif WiFi
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salles');
    }
};
