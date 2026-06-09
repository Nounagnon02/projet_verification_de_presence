<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Filiere;
use App\Models\Ue;
use Illuminate\Database\Seeder;

class UeSeeder extends Seeder
{
    public function run(): void
    {
        $activeAnnee = AnneeAcademique::where('active', true)->first()->id;
        $filieres = Filiere::all()->keyBy('code');

        // ── Définition des UEs par filière ──────────────────────────
        $ues = [
            'IM-L1' => [
                // Semestre 1
                ['code' => 'IM-L1-UE01', 'intitule' => 'Algorithmique et Programmation',      'semestre' => 1, 'volume_horaire' => 60],
                ['code' => 'IM-L1-UE02', 'intitule' => 'Mathématiques Générales',              'semestre' => 1, 'volume_horaire' => 60],
                ['code' => 'IM-L1-UE03', 'intitule' => 'Anglais et Communication',             'semestre' => 1, 'volume_horaire' => 30],
                // Semestre 2
                ['code' => 'IM-L1-UE04', 'intitule' => 'Programmation Orientée Objet',          'semestre' => 2, 'volume_horaire' => 50],
                ['code' => 'IM-L1-UE05', 'intitule' => 'Mathématiques Discrètes',               'semestre' => 2, 'volume_horaire' => 50],
                ['code' => 'IM-L1-UE06', 'intitule' => 'Architecture et Systèmes',              'semestre' => 2, 'volume_horaire' => 40],
            ],
            'IM-L2' => [
                ['code' => 'IM-L2-UE07', 'intitule' => 'Base de Données',                       'semestre' => 3, 'volume_horaire' => 50],
                ['code' => 'IM-L2-UE08', 'intitule' => 'Programmation Web',                     'semestre' => 3, 'volume_horaire' => 50],
                ['code' => 'IM-L2-UE09', 'intitule' => 'Réseaux',                               'semestre' => 3, 'volume_horaire' => 40],
                ['code' => 'IM-L2-UE10', 'intitule' => 'Génie Logiciel Avancé',                  'semestre' => 4, 'volume_horaire' => 50],
                ['code' => 'IM-L2-UE11', 'intitule' => 'Statistiques et Probabilités',           'semestre' => 4, 'volume_horaire' => 40],
                ['code' => 'IM-L2-UE12', 'intitule' => 'Programmation Avancée',                  'semestre' => 4, 'volume_horaire' => 50],
            ],
            'IM-L3' => [
                ['code' => 'IM-L3-UE13', 'intitule' => 'Intelligence Artificielle',              'semestre' => 5, 'volume_horaire' => 50],
                ['code' => 'IM-L3-UE14', 'intitule' => 'Génie Logiciel et Méthodes Agiles',      'semestre' => 5, 'volume_horaire' => 50],
                ['code' => 'IM-L3-UE15', 'intitule' => 'Réseaux Avancés et Sécurité',            'semestre' => 5, 'volume_horaire' => 40],
                ['code' => 'IM-L3-UE16', 'intitule' => 'Projet Professionnel',                   'semestre' => 6, 'volume_horaire' => 40],
                ['code' => 'IM-L3-UE17', 'intitule' => 'Systèmes d\'Information',                'semestre' => 6, 'volume_horaire' => 40],
                ['code' => 'IM-L3-UE18', 'intitule' => 'Applications Mobiles',                   'semestre' => 6, 'volume_horaire' => 40],
            ],
            'MIAGE-M1' => [
                ['code' => 'MIAGE-M1-UE01', 'intitule' => 'Gestion de Projet SI',                'semestre' => 7, 'volume_horaire' => 50],
                ['code' => 'MIAGE-M1-UE02', 'intitule' => 'Java Entreprise',                     'semestre' => 7, 'volume_horaire' => 50],
                ['code' => 'MIAGE-M1-UE03', 'intitule' => 'Base de Données Avancées',            'semestre' => 7, 'volume_horaire' => 40],
                ['code' => 'MIAGE-M1-UE04', 'intitule' => 'Méthodes Agiles',                     'semestre' => 8, 'volume_horaire' => 40],
                ['code' => 'MIAGE-M1-UE05', 'intitule' => 'Big Data et Analytics',               'semestre' => 8, 'volume_horaire' => 50],
                ['code' => 'MIAGE-M1-UE06', 'intitule' => 'Analyse Financière',                  'semestre' => 8, 'volume_horaire' => 40],
            ],
            'MIAGE-M2' => [
                ['code' => 'MIAGE-M2-UE01', 'intitule' => 'Cloud Computing',                     'semestre' => 9, 'volume_horaire' => 50],
                ['code' => 'MIAGE-M2-UE02', 'intitule' => 'DevOps et Intégration Continue',      'semestre' => 9, 'volume_horaire' => 50],
                ['code' => 'MIAGE-M2-UE03', 'intitule' => 'Systèmes d\'Information Décisionnels','semestre' => 9, 'volume_horaire' => 40],
                ['code' => 'MIAGE-M2-UE04', 'intitule' => 'Audit des Systèmes d\'Information',   'semestre' => 10,'volume_horaire' => 40],
                ['code' => 'MIAGE-M2-UE05', 'intitule' => 'Entrepreneuriat et Innovation',       'semestre' => 10,'volume_horaire' => 30],
                ['code' => 'MIAGE-M2-UE06', 'intitule' => 'Mémoire et Stage',                    'semestre' => 10,'volume_horaire' => 20],
            ],
            'GL-M1' => [
                ['code' => 'GL-M1-UE01', 'intitule' => 'Génie Logiciel Avancé',                  'semestre' => 7, 'volume_horaire' => 50],
                ['code' => 'GL-M1-UE02', 'intitule' => 'Architecture Logicielle',                'semestre' => 7, 'volume_horaire' => 50],
                ['code' => 'GL-M1-UE03', 'intitule' => 'Tests et Qualité Logicielle',            'semestre' => 7, 'volume_horaire' => 40],
                ['code' => 'GL-M1-UE04', 'intitule' => 'DevOps et CI/CD',                        'semestre' => 8, 'volume_horaire' => 50],
                ['code' => 'GL-M1-UE05', 'intitule' => 'Gestion de Projet Agile',                'semestre' => 8, 'volume_horaire' => 40],
                ['code' => 'GL-M1-UE06', 'intitule' => 'Frameworks et Technologies Modernes',    'semestre' => 8, 'volume_horaire' => 50],
            ],
            'GL-M2' => [
                ['code' => 'GL-M2-UE01', 'intitule' => 'Architecture Microservices',             'semestre' => 9, 'volume_horaire' => 50],
                ['code' => 'GL-M2-UE02', 'intitule' => 'Cloud et Déploiement',                   'semestre' => 9, 'volume_horaire' => 50],
                ['code' => 'GL-M2-UE03', 'intitule' => 'Sécurité Applicative',                   'semestre' => 9, 'volume_horaire' => 40],
                ['code' => 'GL-M2-UE04', 'intitule' => 'Management d\'Équipe',                   'semestre' => 10,'volume_horaire' => 30],
                ['code' => 'GL-M2-UE05', 'intitule' => 'Innovation et R&D',                      'semestre' => 10,'volume_horaire' => 30],
                ['code' => 'GL-M2-UE06', 'intitule' => 'Projet de Fin d\'Études',                'semestre' => 10,'volume_horaire' => 20],
            ],
            'RIT-L3' => [
                ['code' => 'RIT-L3-UE01', 'intitule' => 'Réseaux Informatiques',                 'semestre' => 5, 'volume_horaire' => 50],
                ['code' => 'RIT-L3-UE02', 'intitule' => 'Télécommunications',                    'semestre' => 5, 'volume_horaire' => 50],
                ['code' => 'RIT-L3-UE03', 'intitule' => 'Sécurité des Réseaux',                  'semestre' => 5, 'volume_horaire' => 40],
                ['code' => 'RIT-L3-UE04', 'intitule' => 'Administration Système et Réseau',      'semestre' => 6, 'volume_horaire' => 40],
                ['code' => 'RIT-L3-UE05', 'intitule' => 'Internet des Objets',                   'semestre' => 6, 'volume_horaire' => 40],
                ['code' => 'RIT-L3-UE06', 'intitule' => 'Projet Intégrateur Réseau',             'semestre' => 6, 'volume_horaire' => 40],
            ],
            'GEA-L1' => [
                ['code' => 'GEA-L1-UE01', 'intitule' => 'Comptabilité Générale',                 'semestre' => 1, 'volume_horaire' => 60],
                ['code' => 'GEA-L1-UE02', 'intitule' => 'Économie Générale',                     'semestre' => 1, 'volume_horaire' => 50],
                ['code' => 'GEA-L1-UE03', 'intitule' => 'Mathématiques Financières',             'semestre' => 1, 'volume_horaire' => 40],
                ['code' => 'GEA-L1-UE04', 'intitule' => 'Gestion des Entreprises',                'semestre' => 2, 'volume_horaire' => 50],
                ['code' => 'GEA-L1-UE05', 'intitule' => 'Droit des Affaires',                    'semestre' => 2, 'volume_horaire' => 40],
                ['code' => 'GEA-L1-UE06', 'intitule' => 'Techniques de Communication',           'semestre' => 2, 'volume_horaire' => 30],
            ],
            'GEA-L2' => [
                ['code' => 'GEA-L2-UE01', 'intitule' => 'Comptabilité Analytique',               'semestre' => 3, 'volume_horaire' => 50],
                ['code' => 'GEA-L2-UE02', 'intitule' => 'Fiscalité',                             'semestre' => 3, 'volume_horaire' => 40],
                ['code' => 'GEA-L2-UE03', 'intitule' => 'Marketing Fondamental',                 'semestre' => 3, 'volume_horaire' => 40],
                ['code' => 'GEA-L2-UE04', 'intitule' => 'Gestion des Ressources Humaines',       'semestre' => 4, 'volume_horaire' => 40],
                ['code' => 'GEA-L2-UE05', 'intitule' => 'Contrôle de Gestion',                   'semestre' => 4, 'volume_horaire' => 50],
                ['code' => 'GEA-L2-UE06', 'intitule' => 'Statistiques Appliquées',               'semestre' => 4, 'volume_horaire' => 40],
            ],
        ];

        $count = 0;
        foreach ($ues as $filiereCode => $filiereUes) {
            $filiere = $filieres->get($filiereCode);
            if (!$filiere) {
                $this->command->warn("Filière {$filiereCode} introuvable, UEs ignorées.");
                continue;
            }

            foreach ($filiereUes as $data) {
                Ue::firstOrCreate(
                    ['code' => $data['code']],
                    [
                        'intitule'       => $data['intitule'],
                        'filiere_id'     => $filiere->id,
                        'annee_id'       => $activeAnnee,
                        'semestre'       => $data['semestre'],
                        'volume_horaire' => $data['volume_horaire'],
                    ]
                );
                $count++;
            }
        }

        $this->command->info("UEs créées : {$count}");
    }
}
