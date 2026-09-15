<?php

namespace Tests\Feature;

use App\Models\Ec;
use App\Models\Evenement;
use App\Models\QrCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * L'application vit à l'heure du Bénin.
 *
 * Elle a tourné en UTC alors que les heures de cours sont saisies à l'heure
 * locale : un cours finissant à 10h00 ne recevait son QR qu'à 10h45 heure
 * locale, une fois les étudiants partis.
 */
class FuseauHoraireTest extends TestCase
{
    private const MIGRATION = '2026_09_14_090000_horodatages_a_l_heure_du_benin.php';

    public function test_un_cours_saisi_a_l_heure_locale_recoit_son_qr_avant_sa_fin(): void
    {
        config(['presence.scan.minutes_avant_fin' => 15, 'presence.scan.minutes_apres_fin' => 10]);

        // 10h20 au Bénin, soit 09h20 UTC.
        $this->travelTo(Carbon::parse('2026-03-10 10:20:00', 'Africa/Porto-Novo'));

        $ue = $this->uneUe();
        $ec = Ec::create([
            'ue_id' => $ue->id, 'code' => 'TZ' . Str::random(5), 'intitule' => 'Cours fuseau', 'volume_horaire' => 20,
        ]);

        // Cours de 09h30 à 10h25, heure locale : scan ouvert de 10h10 à 10h35.
        // En UTC, le serveur l'aurait ouvert à 11h10 heure locale.
        $seance = Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $ue->filiere_id, 'annee_id' => $ue->annee_id,
            'date' => '2026-03-10', 'heure_debut' => '09:30', 'heure_fin' => '10:25', 'statut' => 'planifie',
        ]);

        $this->artisan('qrcode:auto-generate')->assertSuccessful();

        $this->assertTrue(QrCode::where('evenement_id', $seance->id)->where('actif', true)->exists());
    }

    public function test_la_migration_passe_les_horodatages_utc_a_l_heure_locale(): void
    {
        $migration = require database_path('migrations/' . self::MIGRATION);

        $id = $this->etablissementHorodate('2026-03-10 08:21:30');
        $seance = $this->seanceDeReference();

        $migration->up();

        $this->assertSame('2026-03-10 09:21:30', $this->horodatage($id));
        // Date et heures de cours : déjà à l'heure locale, intouchées.
        $this->assertSame('2026-03-12', $seance->fresh()->date->format('Y-m-d'));
        $this->assertSame('10:00:00', $seance->fresh()->heure_fin);

        $migration->down();

        $this->assertSame('2026-03-10 08:21:30', $this->horodatage($id));
    }

    public function test_la_migration_ne_decale_rien_si_l_application_reste_en_utc(): void
    {
        $migration = require database_path('migrations/' . self::MIGRATION);
        config(['app.timezone' => 'UTC']);

        $id = $this->etablissementHorodate('2026-03-10 08:21:30');
        $migration->up();

        $this->assertSame('2026-03-10 08:21:30', $this->horodatage($id));
    }

    private function etablissementHorodate(string $instant): int
    {
        $sfx = Str::random(6);

        return DB::table('etablissements')->insertGetId([
            'code' => 'TZ' . $sfx, 'nom' => 'Fuseau ' . $sfx, 'email' => strtolower("tz.{$sfx}@test.local"),
            'created_at' => $instant, 'updated_at' => $instant,
        ]);
    }

    private function horodatage(int $id): string
    {
        return (string) DB::table('etablissements')->where('id', $id)->value('created_at');
    }

    private function seanceDeReference(): Evenement
    {
        $ue = $this->uneUe();
        $ec = Ec::create([
            'ue_id' => $ue->id, 'code' => 'TZR' . Str::random(5), 'intitule' => 'Référence', 'volume_horaire' => 20,
        ]);

        return Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $ue->filiere_id, 'annee_id' => $ue->annee_id,
            'date' => '2026-03-12', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'planifie',
        ]);
    }
}
