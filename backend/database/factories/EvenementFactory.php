<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Evenement;
use App\Models\Filiere;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evenement>
 */
class EvenementFactory extends Factory
{
    protected $model = Evenement::class;

    public function definition(): array
    {
        return [
            'ec_id'       => Ec::factory(),
            'filiere_id'  => Filiere::factory(),
            'annee_id'    => AnneeAcademique::factory(),
            'date'        => Carbon::today()->toDateString(),
            'heure_debut' => '08:00:00',
            'heure_fin'   => '10:00:00',
            'salle'       => 'A-101',
            'statut'      => 'planifie',
        ];
    }

    /**
     * Evenement rattache a un EC existant, avec la filiere et l'annee de son UE.
     *
     * A preferer systematiquement a l'etat par defaut : sans cela, ec_id,
     * filiere_id et annee_id viennent de trois factories independantes, et
     * l'evenement decrit un cours qui n'appartient a personne. La verification
     * d'inscription du scan (etape 5) rejette alors tout etudiant.
     */
    public function pourEc(Ec $ec): static
    {
        $ue = $ec->ue;

        return $this->state(fn () => [
            'ec_id'      => $ec->id,
            'filiere_id' => $ue->filiere_id,
            'annee_id'   => $ue->annee_id,
        ]);
    }

    /**
     * Evenement dont la fenetre de prise de presence est ouverte maintenant.
     *
     * La fenetre est ancree sur l'heure de FIN (config/presence.php) :
     * [fin - 15 min, fin + 10 min]. Un evenement « en cours » au sens naif —
     * debut passe, fin a venir — n'est donc PAS scannable. D'ou cet etat, qui
     * place la fin juste devant l'instant present.
     */
    public function fenetreOuverte(): static
    {
        $fin = Carbon::now()->addMinutes(5);

        return $this->state(fn () => [
            'date'        => $fin->toDateString(),
            'heure_debut' => $fin->copy()->subHours(2)->format('H:i:s'),
            'heure_fin'   => $fin->format('H:i:s'),
        ]);
    }

    /** Evenement termine et hors fenetre : tout scan doit etre refuse. */
    public function passe(int $jours = 3): static
    {
        return $this->state(fn () => [
            'date'   => Carbon::now()->subDays($jours)->toDateString(),
            'statut' => 'termine',
        ]);
    }
}
