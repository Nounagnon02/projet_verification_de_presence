<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Passe les horodatages déjà enregistrés de l'UTC à l'heure du Bénin.
 *
 * L'application tournait en UTC alors que les heures de cours sont saisies à
 * l'heure locale. La fenêtre de scan s'ouvrait donc une heure trop tard, après
 * la fin réelle du cours, et les heures de scan s'affichaient et s'exportaient
 * avec une heure de retard. Le fuseau est désormais config('app.timezone'),
 * Africa/Porto-Novo par défaut.
 *
 * Les colonnes « timestamp without time zone » ne portent aucun fuseau :
 * Laravel y écrit l'heure dans le fuseau de l'application. Les valeurs déjà
 * présentes ont été écrites en UTC ; elles sont décalées une fois pour rester
 * justes une fois relues à l'heure locale. Les colonnes sont lues dans le
 * schéma plutôt que listées, pour n'en oublier aucune.
 *
 * Les colonnes DATE et TIME (date et heures des cours) ne bougent pas : elles
 * étaient déjà saisies à l'heure locale.
 *
 * Le décalage est celui du fuseau configuré : constant au Bénin (UTC+1, sans
 * heure d'été), nul si l'application reste en UTC. Pour revenir à l'UTC,
 * annuler cette migration AVANT de changer le fuseau, sinon le retour ne
 * décalerait rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->decaler(1);
    }

    public function down(): void
    {
        $this->decaler(-1);
    }

    private function decaler(int $sens): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $minutes = now(config('app.timezone'))->utcOffset() * $sens;

        if ($minutes === 0) {
            return;
        }

        $colonnes = collect(DB::select("
            select table_name, column_name
            from information_schema.columns
            where table_schema = current_schema()
              and data_type = 'timestamp without time zone'
            order by table_name, column_name
        "));

        // Une requête par table, toutes ses colonnes d'un coup. NULL + intervalle
        // reste NULL : les valeurs absentes ne sont pas inventées.
        foreach ($colonnes->groupBy('table_name') as $table => $colonnesDeLaTable) {
            $affectations = $colonnesDeLaTable
                ->map(fn ($c) => sprintf('"%1$s" = "%1$s" + interval \'1 minute\' * %2$d', $c->column_name, $minutes))
                ->implode(', ');

            DB::statement(sprintf('update "%s" set %s', $table, $affectations));
        }
    }
};
