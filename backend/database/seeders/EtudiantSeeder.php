<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use Illuminate\Database\Seeder;

class EtudiantSeeder extends Seeder
{
    public function run(): void
    {
        $activeAnnee = AnneeAcademique::where('active', true)->first()->id;
        $filieres = Filiere::all()->keyBy('code');

        // Noms et prénoms réalistes (Bénin / Afrique de l'Ouest)
        $noms = ['ADJOVI', 'AGOSSOU', 'AHOUANDJINOU', 'AKAKPO', 'ALIDOU', 'AMOUSSOU', 'ASSABA',
                 'ATCHADE', 'BADA', 'BALOGOUN', 'BOKO', 'BOSSIN', 'CHABI', 'DAGNON', 'DAHOUNDO',
                 'DJIBRIL', 'DOSSOU', 'GBAGUIDI', 'GBESSI', 'GUEDENON', 'HESSOU', 'HOUNKPATIN',
                 'HOUNDJO', 'HOUESSOU', 'IDOHOU', 'KINNOU', 'KODJOGBE', 'KOUASSI', 'LAWANI',
                 'LOKO', 'MADIROU', 'MAMAN', 'MENSAH', 'MONTCHO', 'NAGO', 'NONVI', 'NOUMONVI',
                 'OGOUNDELE', 'OKE', 'OLOGBENLA', 'OUSSENI', 'PADONOU', 'QUENUM', 'SAGBO',
                 'SALAMI', 'SOTON', 'SOVI', 'TOHOUEGNON', 'TOKPLONOU', 'TONATO', 'TONON',
                 'VISSOU', 'YABI', 'YAKPE', 'ZANNOU', 'ZINSOU', 'ZOHOUN'];

        $prenoms = ['Abraham', 'Abdel', 'Adélia', 'Adil', 'Afiss', 'Agnès', 'Alain', 'Albert',
                    'Alexis', 'Alice', 'Amandine', 'Aminata', 'Anaïs', 'André', 'Ange', 'Anicet',
                    'Arnaud', 'Aubin', 'Aurélie', 'Axelle', 'Bénédicte', 'Boris', 'Briand',
                    'Calvin', 'Camille', 'Catherine', 'Cédric', 'Célia', 'Christelle', 'Christophe',
                    'Clarisse', 'Claude', 'Clément', 'Constant', 'Cybelle', 'Cynthia', 'Damien',
                    'Daniel', 'David', 'Déborah', 'Delphine', 'Denis', 'Diane', 'Dieudonné',
                    'Dimitri', 'Dorcas', 'Eddy', 'Edwige', 'Elysée', 'Emmanuel', 'Eric', 'Estelle',
                    'Esther', 'Eva', 'Fabrice', 'Félicien', 'Fidèle', 'Florence', 'Florian',
                    'Fortuné', 'Franck', 'Frédéric', 'Gabriel', 'Gaël', 'Georges', 'Ghislain',
                    'Gildas', 'Grégoire', 'Grâce', 'Guillaume', 'Hector', 'Hélène', 'Hermann',
                    'Hubert', 'Irène', 'Isaac', 'Ismaël', 'Jacqueline', 'Jean', 'Jessica',
                    'Joachim', 'Joël', 'Jonathan', 'José', 'Joseph', 'Josiane', 'Judicaël',
                    'Jules', 'Julien', 'Justin', 'Karine', 'Kevin', 'Laeticia', 'Laurent',
                    'Léonard', 'Lorraine', 'Louis', 'Luc', 'Lucien', 'Ludovic', 'Marc', 'Marcel',
                    'Marguerite', 'Marie', 'Martial', 'Martin', 'Matthieu', 'Maurice', 'Médard',
                    'Michaël', 'Michel', 'Moïse', 'Murielle', 'Nadège', 'Narcisse', 'Nathalie',
                    'Nestor', 'Nicole', 'Noël', 'Olivier', 'Olympe', 'Oscar', 'Pacôme',
                    'Pascal', 'Patrick', 'Paul', 'Philippe', 'Pierre', 'Prisca', 'Prudence',
                    'Rachel', 'Rachid', 'Raoul', 'Raphaël', 'Régine', 'Renaud', 'Richard',
                    'Rita', 'Rodrigue', 'Roland', 'Romaric', 'Rufin', 'Sabine', 'Sandra',
                    'Serge', 'Sébastien', 'Séverin', 'Simon', 'Sonia', 'Stanislas', 'Stéphane',
                    'Sylvain', 'Sylvie', 'Tanguy', 'Théophile', 'Thierry', 'Thomas', 'Ulysse',
                    'Valentin', 'Valérie', 'Véronique', 'Victor', 'Victorin', 'Vincent', 'Wilfried',
                    'William', 'Yannick', 'Yves', 'Zacharie'];

        // Mapping filière -> nombre d'étudiants à créer
        $studentCounts = [
            'IM-L1'    => 8,
            'IM-L2'    => 7,
            'IM-L3'    => 6,
            'MIAGE-M1' => 6,
            'MIAGE-M2' => 5,
            'GL-M1'    => 6,
            'GL-M2'    => 5,
            'RIT-L3'   => 5,
            'GEA-L1'   => 5,
            'GEA-L2'   => 5,
        ];

        $total = 0;
        shuffle($noms);
        shuffle($prenoms);

        $usedMatricules = [];
        $usedEmails = [];
        $usedIdentifiants = [];

        foreach ($studentCounts as $filiereCode => $count) {
            $filiere = $filieres->get($filiereCode);
            if (!$filiere) {
                $this->command->warn("Filière {$filiereCode} introuvable.");
                continue;
            }

            // Déterminer l'année d'étude en fonction du niveau
            $niveau = $filiere->niveau; // L1, L2, L3, M1, M2
            $promotionYear = match ($niveau) {
                'L1' => '24', // Entré en 2024-2025
                'L2' => '23', // Entré en 2023-2024
                'L3' => '22', // Entré en 2022-2023
                'M1' => '24', // Entré en 2024-2025
                'M2' => '23', // Entré en 2023-2024
                default => '24',
            };

            for ($i = 0; $i < $count; $i++) {
                $nom = $noms[array_rand($noms)];
                $prenom = $prenoms[array_rand($prenoms)];

                // Générer un matricule unique
                do {
                    $matricule = $promotionYear . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                } while (in_array($matricule, $usedMatricules));
                $usedMatricules[] = $matricule;

                // Générer un email unique
                $emailBase = strtolower(str_replace([' ', '\'', '-'], '', $prenom . '.' . $nom));
                do {
                    $email = $emailBase . mt_rand(1, 999) . '@etu.uac.bj';
                } while (in_array($email, $usedEmails));
                $usedEmails[] = $email;

                // Identifiant unique déterministe
                $identifiant = $matricule;
                while (in_array($identifiant, $usedIdentifiants)) {
                    $identifiant = $matricule . chr(rand(65, 90));
                }
                $usedIdentifiants[] = $identifiant;

                Etudiant::create([
                    'nom'               => $nom,
                    'prenom'            => $prenom,
                    'matricule'         => $matricule,
                    'filiere_id'        => $filiere->id,
                    'annee_id'          => $activeAnnee,
                    'email'             => $email,
                    'telephone'         => '+229 ' . mt_rand(61000000, 97999999),
                    'identifiant_unique'=> $identifiant,
                    'points'            => mt_rand(0, 100),
                ]);
                $total++;
            }
        }

        $this->command->info("Étudiants créés : {$total}");
    }
}
