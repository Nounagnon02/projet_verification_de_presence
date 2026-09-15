<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programmes, et code de filière unique dans son établissement.
 *
 * Une « filière » est un programme à un niveau : IM-L1, IM-L2 et IM-L3 sont
 * trois filières d'un même programme, IM. Rien ne le disait. Le niveau était
 * écrit trois fois — dans le code, dans le champ niveau, dans l'intitulé — et
 * l'écran ne pouvait pas regrouper les filières d'un programme.
 *
 * Le code d'une filière était unique dans toute la base : deux facultés ne
 * pouvaient pas avoir chacune leur « IM-L1 ». Il l'est désormais dans son
 * établissement.
 *
 * Reprise : chaque filière existante est rattachée au programme que désigne
 * son code privé du niveau (« IM-L2 » → IM), l'intitulé perdant le « (L2) »
 * final. La règle est recopiée ici plutôt qu'appelée depuis le modèle : une
 * migration ne doit pas changer de comportement quand le code applicatif évolue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->nullOnDelete();
            $table->string('code', 20);
            $table->string('intitule');
            $table->timestamps();

            $table->unique(['etablissement_id', 'code']);
        });

        Schema::table('filieres', function (Blueprint $table) {
            $table->foreignId('programme_id')->nullable()->after('id')->constrained('programmes')->nullOnDelete();
            $table->dropUnique('filieres_code_unique');
            $table->unique(['etablissement_id', 'code'], 'filieres_etablissement_code_unique');
        });

        foreach (DB::table('filieres')->orderBy('id')->get() as $filiere) {
            [$code, $intitule] = $this->programmeDeduit($filiere);

            $programmeId = DB::table('programmes')
                ->where('etablissement_id', $filiere->etablissement_id)
                ->where('code', $code)
                ->value('id');

            $programmeId ??= DB::table('programmes')->insertGetId([
                'etablissement_id' => $filiere->etablissement_id,
                'code'             => $code,
                'intitule'         => $intitule,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            DB::table('filieres')->where('id', $filiere->id)->update(['programme_id' => $programmeId]);
        }
    }

    public function down(): void
    {
        Schema::table('filieres', function (Blueprint $table) {
            $table->dropUnique('filieres_etablissement_code_unique');
            // Échoue si deux établissements partagent désormais un code : à régler
            // avant de revenir en arrière.
            $table->unique('code');
            $table->dropConstrainedForeignId('programme_id');
        });

        Schema::dropIfExists('programmes');
    }

    /** @return array{0: string, 1: string} code et intitulé du programme */
    private function programmeDeduit(object $filiere): array
    {
        $niveau = preg_quote((string) $filiere->niveau, '/');

        $code = preg_replace("/[-_ ]?{$niveau}$/i", '', (string) $filiere->code) ?? '';
        $code = $code !== '' ? $code : (string) $filiere->code;

        // « Informatique et Mathématiques (L1) » ; « Informatique et Multimédia — L1 (Démonstration) ».
        $intitule = preg_replace("/\s*\(\s*{$niveau}\s*\)\s*$/iu", '', (string) $filiere->intitule) ?? '';
        $intitule = trim(preg_replace("/\s*[—–-]\s*{$niveau}\b/u", '', $intitule) ?? '');

        return [mb_strtoupper(mb_substr($code, 0, 20)), $intitule !== '' ? $intitule : (string) $filiere->intitule];
    }
};
