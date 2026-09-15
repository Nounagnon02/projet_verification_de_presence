<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cours communs : une UE suivie par plusieurs filières d'un même niveau.
 *
 * L'emploi du temps de l'IFRI en L2 réunit GL, IM, IA, SI et SE&IoT : un cours
 * commun devait être dupliqué par filière — deux séances, deux QR codes, et un
 * contrôle de salle qui interdisait de les mettre dans le même amphi.
 *
 * ue_filiere liste les filières qui suivent l'UE, porteuse comprise ;
 * ues.filiere_id reste la filière porteuse (celle qui l'a créée, dont
 * l'établissement cloisonne l'UE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ue_filiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ue_id')->constrained('ues')->cascadeOnDelete();
            $table->foreignId('filiere_id')->constrained('filieres')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ue_id', 'filiere_id']);
        });

        DB::statement('insert into ue_filiere (ue_id, filiere_id, created_at, updated_at) select id, filiere_id, now(), now() from ues');
    }

    public function down(): void
    {
        Schema::dropIfExists('ue_filiere');
    }
};
