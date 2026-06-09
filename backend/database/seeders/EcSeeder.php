<?php

namespace Database\Seeders;

use App\Models\Ec;
use App\Models\Ue;
use Illuminate\Database\Seeder;

class EcSeeder extends Seeder
{
    public function run(): void
    {
        $ues = Ue::all();

        // Définition des ECs par code d'UE
        $ecs = [
            // IM-L1
            'IM-L1-UE01' => [
                ['code' => 'ALG-FOND',  'intitule' => 'Algorithmique Fondamentale',   'volume_horaire' => 30],
                ['code' => 'PROG-C',    'intitule' => 'Programmation en C',            'volume_horaire' => 30],
            ],
            'IM-L1-UE02' => [
                ['code' => 'ALGEBRE',   'intitule' => 'Algèbre',                       'volume_horaire' => 30],
                ['code' => 'ANALYSE',   'intitule' => 'Analyse Mathématique',          'volume_horaire' => 30],
            ],
            'IM-L1-UE03' => [
                ['code' => 'ANGL-SCI',  'intitule' => 'Anglais Scientifique',          'volume_horaire' => 15],
                ['code' => 'TECH-EXPR', 'intitule' => 'Techniques d\'Expression',      'volume_horaire' => 15],
            ],
            'IM-L1-UE04' => [
                ['code' => 'POO-JAVA',  'intitule' => 'POO en Java',                   'volume_horaire' => 30],
                ['code' => 'GEN-LOG',   'intitule' => 'Introduction au Génie Logiciel','volume_horaire' => 20],
            ],
            'IM-L1-UE05' => [
                ['code' => 'LOG-MATH',  'intitule' => 'Logique Mathématique',          'volume_horaire' => 25],
                ['code' => 'TH-GRAPH',  'intitule' => 'Théorie des Graphes',           'volume_horaire' => 25],
            ],
            'IM-L1-UE06' => [
                ['code' => 'ARCHI-ORD', 'intitule' => 'Architecture des Ordinateurs',  'volume_horaire' => 20],
                ['code' => 'SYS-EXP',   'intitule' => 'Systèmes d\'Exploitation',      'volume_horaire' => 20],
            ],

            // IM-L2
            'IM-L2-UE07' => [
                ['code' => 'MODEL-BD',  'intitule' => 'Modélisation de Données',       'volume_horaire' => 25],
                ['code' => 'SQL-SGBD',  'intitule' => 'SQL et SGBD',                   'volume_horaire' => 25],
            ],
            'IM-L2-UE08' => [
                ['code' => 'HTML-CSS-JS',  'intitule' => 'HTML/CSS/JavaScript',        'volume_horaire' => 25],
                ['code' => 'FRAM-WEB',     'intitule' => 'Frameworks Web',             'volume_horaire' => 25],
            ],
            'IM-L2-UE09' => [
                ['code' => 'TCP-IP',    'intitule' => 'TCP/IP et Protocoles',          'volume_horaire' => 20],
                ['code' => 'ADM-RES',   'intitule' => 'Administration Réseau',         'volume_horaire' => 20],
            ],
            'IM-L2-UE10' => [
                ['code' => 'UML-CONC',  'intitule' => 'UML et Conception',             'volume_horaire' => 25],
                ['code' => 'TEST-QUAL', 'intitule' => 'Tests et Qualité',              'volume_horaire' => 25],
            ],
            'IM-L2-UE11' => [
                ['code' => 'STAT-DESC', 'intitule' => 'Statistiques Descriptives',     'volume_horaire' => 20],
                ['code' => 'PROBAS',    'intitule' => 'Probabilités',                  'volume_horaire' => 20],
            ],
            'IM-L2-UE12' => [
                ['code' => 'PYTHON',    'intitule' => 'Programmation Python',          'volume_horaire' => 25],
                ['code' => 'CPP-PLUS',  'intitule' => 'Programmation C++',             'volume_horaire' => 25],
            ],

            // IM-L3
            'IM-L3-UE13' => [
                ['code' => 'IA-FOND',   'intitule' => 'Intelligence Artificielle Fondamentale', 'volume_horaire' => 25],
                ['code' => 'ML-BASE',   'intitule' => 'Machine Learning',              'volume_horaire' => 25],
            ],
            'IM-L3-UE14' => [
                ['code' => 'METHOD-AGILES', 'intitule' => 'Méthodes Agiles',           'volume_horaire' => 25],
                ['code' => 'DEVOPS',        'intitule' => 'DevOps',                    'volume_horaire' => 25],
            ],
            'IM-L3-UE15' => [
                ['code' => 'SECUR-RES', 'intitule' => 'Sécurité Réseau',               'volume_horaire' => 20],
                ['code' => 'CLOUD-COMP', 'intitule' => 'Cloud Computing',              'volume_horaire' => 20],
            ],
            'IM-L3-UE16' => [
                ['code' => 'PFE',       'intitule' => 'Projet de Fin d\'Études',       'volume_horaire' => 20],
                ['code' => 'ENTREPR',   'intitule' => 'Entrepreneuriat',               'volume_horaire' => 20],
            ],
            'IM-L3-UE17' => [
                ['code' => 'ERP-SAP',   'intitule' => 'ERP',                           'volume_horaire' => 20],
                ['code' => 'SI-DECIS',  'intitule' => 'SI Décisionnels',              'volume_horaire' => 20],
            ],
            'IM-L3-UE18' => [
                ['code' => 'ANDROID',   'intitule' => 'Développement Android',         'volume_horaire' => 20],
                ['code' => 'SWIFT-IOS', 'intitule' => 'Développement iOS',             'volume_horaire' => 20],
            ],

            // MIAGE-M1
            'MIAGE-M1-UE01' => [
                ['code' => 'GEST-PROJ-SI', 'intitule' => 'Gestion de Projet SI',       'volume_horaire' => 25],
                ['code' => 'PLANIF-STRAT', 'intitule' => 'Planification Stratégique',  'volume_horaire' => 25],
            ],
            'MIAGE-M1-UE02' => [
                ['code' => 'J2EE-SPRING', 'intitule' => 'Java/J2EE et Spring',         'volume_horaire' => 30],
                ['code' => 'JPA-HIBERNATE','intitule' => 'JPA et Hibernate',           'volume_horaire' => 20],
            ],
            'MIAGE-M1-UE03' => [
                ['code' => 'BD-ADV',   'intitule' => 'BD Avancées',                    'volume_horaire' => 20],
                ['code' => 'NOSQL',     'intitule' => 'NoSQL',                          'volume_horaire' => 20],
            ],
            'MIAGE-M1-UE04' => [
                ['code' => 'SCRUM-KANBAN', 'intitule' => 'Scrum et Kanban',            'volume_horaire' => 20],
                ['code' => 'GEST-CHG',  'intitule' => 'Gestion du Changement',         'volume_horaire' => 20],
            ],
            'MIAGE-M1-UE05' => [
                ['code' => 'DATA-MINING','intitule' => 'Data Mining',                  'volume_horaire' => 25],
                ['code' => 'BIG-DATA',  'intitule' => 'Big Data Technologies',         'volume_horaire' => 25],
            ],
            'MIAGE-M1-UE06' => [
                ['code' => 'ANA-FIN',  'intitule' => 'Analyse Financière',             'volume_horaire' => 20],
                ['code' => 'COMPTA-GEST','intitule' => 'Comptabilité de Gestion',      'volume_horaire' => 20],
            ],

            // MIAGE-M2
            'MIAGE-M2-UE01' => [
                ['code' => 'CLOUD-AWS', 'intitule' => 'Cloud AWS/Azure',               'volume_horaire' => 25],
                ['code' => 'VIRT-DOCK', 'intitule' => 'Virtualisation et Docker',       'volume_horaire' => 25],
            ],
            'MIAGE-M2-UE02' => [
                ['code' => 'CI-CD',     'intitule' => 'CI/CD Pipelines',                'volume_horaire' => 25],
                ['code' => 'AUTOM-ADM', 'intitule' => 'Automatisation et Admin Sys',    'volume_horaire' => 25],
            ],
            'MIAGE-M2-UE03' => [
                ['code' => 'DATA-WARE', 'intitule' => 'Data Warehouse',                 'volume_horaire' => 20],
                ['code' => 'BI-TOOLS',  'intitule' => 'Outils BI',                      'volume_horaire' => 20],
            ],
            'MIAGE-M2-UE04' => [
                ['code' => 'AUDIT-SI',  'intitule' => 'Audit SI',                       'volume_horaire' => 20],
                ['code' => 'GOUV-SI',   'intitule' => 'Gouvernance SI',                 'volume_horaire' => 20],
            ],
            'MIAGE-M2-UE05' => [
                ['code' => 'ENTREP-INNOV', 'intitule' => 'Entrepreneuriat et Innovation','volume_horaire' => 15],
                ['code' => 'BIZ-MODEL', 'intitule' => 'Business Model',                 'volume_horaire' => 15],
            ],
            'MIAGE-M2-UE06' => [
                ['code' => 'MEMOIRE-STAGE', 'intitule' => 'Mémoire de Stage',           'volume_horaire' => 10],
                ['code' => 'SOUTENANCE', 'intitule' => 'Préparation Soutenance',        'volume_horaire' => 10],
            ],

            // GL-M1
            'GL-M1-UE01' => [
                ['code' => 'GL-ADV',    'intitule' => 'Génie Logiciel Avancé',          'volume_horaire' => 25],
                ['code' => 'PATR-DESIGN','intitule' => 'Patrons de Conception',         'volume_horaire' => 25],
            ],
            'GL-M1-UE02' => [
                ['code' => 'ARCHI-MICRO','intitule' => 'Architecture Microservices',    'volume_horaire' => 25],
                ['code' => 'ARCHI-HEX',  'intitule' => 'Architecture Hexagonale',       'volume_horaire' => 25],
            ],
            'GL-M1-UE03' => [
                ['code' => 'TEST-AUTO', 'intitule' => 'Tests Automatisés',              'volume_horaire' => 20],
                ['code' => 'QA-PROC',   'intitule' => 'Processus Qualité',             'volume_horaire' => 20],
            ],
            'GL-M1-UE04' => [
                ['code' => 'PIPELINE-CI', 'intitule' => 'Pipelines CI/CD',              'volume_horaire' => 25],
                ['code' => 'INFRA-CODE',  'intitule' => 'Infrastructure as Code',       'volume_horaire' => 25],
            ],
            'GL-M1-UE05' => [
                ['code' => 'AGIL-SCRUM',  'intitule' => 'Agile et Scrum',              'volume_horaire' => 20],
                ['code' => 'GEST-RISQ',   'intitule' => 'Gestion des Risques Projet',  'volume_horaire' => 20],
            ],
            'GL-M1-UE06' => [
                ['code' => 'REACT-MODERN', 'intitule' => 'React et Frameworks Modernes','volume_horaire' => 25],
                ['code' => 'NODE-BACK',    'intitule' => 'Node.js et Backend',         'volume_horaire' => 25],
            ],

            // GL-M2
            'GL-M2-UE01' => [
                ['code' => 'MICRO-SERV',  'intitule' => 'Microservices Avancés',        'volume_horaire' => 25],
                ['code' => 'API-DESIGN',  'intitule' => 'API Design et REST',           'volume_horaire' => 25],
            ],
            'GL-M2-UE02' => [
                ['code' => 'KUBERNETES',  'intitule' => 'Kubernetes et Orchestration',  'volume_horaire' => 25],
                ['code' => 'DEPLOY-STRAT','intitule' => 'Stratégies de Déploiement',    'volume_horaire' => 25],
            ],
            'GL-M2-UE03' => [
                ['code' => 'SECUR-APP',   'intitule' => 'Sécurité Applicative',         'volume_horaire' => 20],
                ['code' => 'OWASP-TOP10', 'intitule' => 'OWASP Top 10',                 'volume_horaire' => 20],
            ],
            'GL-M2-UE04' => [
                ['code' => 'MANAG-EQUIP', 'intitule' => 'Management d\'Équipe',         'volume_horaire' => 15],
                ['code' => 'LEADERSHIP',  'intitule' => 'Leadership',                   'volume_horaire' => 15],
            ],
            'GL-M2-UE05' => [
                ['code' => 'INNOV-RD',    'intitule' => 'Innovation et R&D',            'volume_horaire' => 15],
                ['code' => 'VEILLE-TECH', 'intitule' => 'Veille Technologique',         'volume_horaire' => 15],
            ],
            'GL-M2-UE06' => [
                ['code' => 'PFE-GL',      'intitule' => 'Projet de Fin d\'Études',      'volume_horaire' => 10],
                ['code' => 'MEMOIRE-GL',  'intitule' => 'Mémoire',                      'volume_horaire' => 10],
            ],

            // RIT-L3
            'RIT-L3-UE01' => [
                ['code' => 'RES-INFRA',   'intitule' => 'Infrastructure Réseau',        'volume_horaire' => 25],
                ['code' => 'PROT-RES',    'intitule' => 'Protocoles Réseau',            'volume_horaire' => 25],
            ],
            'RIT-L3-UE02' => [
                ['code' => 'TELECOM',     'intitule' => 'Télécommunications',           'volume_horaire' => 25],
                ['code' => 'FIBRE-OPT',   'intitule' => 'Fibre Optique',                'volume_horaire' => 25],
            ],
            'RIT-L3-UE03' => [
                ['code' => 'SECUR-PERIM', 'intitule' => 'Sécurité Périmétrique',        'volume_horaire' => 20],
                ['code' => 'CRYPTO',      'intitule' => 'Cryptographie',                'volume_horaire' => 20],
            ],
            'RIT-L3-UE04' => [
                ['code' => 'ADM-LINUX',   'intitule' => 'Administration Linux',         'volume_horaire' => 20],
                ['code' => 'ADM-WINDOWS', 'intitule' => 'Administration Windows Server','volume_horaire' => 20],
            ],
            'RIT-L3-UE05' => [
                ['code' => 'IOT-PROTO',   'intitule' => 'IoT et Protocoles Associés',   'volume_horaire' => 20],
                ['code' => 'IOT-SECUR',   'intitule' => 'Sécurité IoT',                 'volume_horaire' => 20],
            ],
            'RIT-L3-UE06' => [
                ['code' => 'PROJ-RES',    'intitule' => 'Projet Intégrateur Réseau',    'volume_horaire' => 20],
                ['code' => 'STAGE-RIT',   'intitule' => 'Stage Professionnel',          'volume_horaire' => 20],
            ],

            // GEA-L1
            'GEA-L1-UE01' => [
                ['code' => 'COMPT-GEN',  'intitule' => 'Comptabilité Générale',         'volume_horaire' => 30],
                ['code' => 'COMPT-SOC',  'intitule' => 'Comptabilité des Sociétés',     'volume_horaire' => 30],
            ],
            'GEA-L1-UE02' => [
                ['code' => 'MICRO-ECO',  'intitule' => 'Microéconomie',                 'volume_horaire' => 25],
                ['code' => 'MACRO-ECO',  'intitule' => 'Macroéconomie',                 'volume_horaire' => 25],
            ],
            'GEA-L1-UE03' => [
                ['code' => 'MATH-FIN',   'intitule' => 'Mathématiques Financières',     'volume_horaire' => 20],
                ['code' => 'STAT-DESC-GEA','intitule' => 'Statistiques Descriptives',   'volume_horaire' => 20],
            ],
            'GEA-L1-UE04' => [
                ['code' => 'GEST-ENTR',   'intitule' => 'Gestion d\'Entreprise',        'volume_horaire' => 25],
                ['code' => 'ORGA-ENTR',   'intitule' => 'Organisation d\'Entreprise',   'volume_horaire' => 25],
            ],
            'GEA-L1-UE05' => [
                ['code' => 'DROIT-AFF',   'intitule' => 'Droit des Affaires',           'volume_horaire' => 20],
                ['code' => 'DROIT-CONT',  'intitule' => 'Droit des Contrats',           'volume_horaire' => 20],
            ],
            'GEA-L1-UE06' => [
                ['code' => 'COMM-ECRITE', 'intitule' => 'Communication Écrite',        'volume_horaire' => 15],
                ['code' => 'COMM-ORALE',  'intitule' => 'Communication Orale',         'volume_horaire' => 15],
            ],

            // GEA-L2
            'GEA-L2-UE01' => [
                ['code' => 'COMPT-ANAL',  'intitule' => 'Comptabilité Analytique',      'volume_horaire' => 25],
                ['code' => 'BUDGET',      'intitule' => 'Gestion Budgétaire',           'volume_horaire' => 25],
            ],
            'GEA-L2-UE02' => [
                ['code' => 'FISCAL-ENTR', 'intitule' => 'Fiscalité de l\'Entreprise',   'volume_horaire' => 20],
                ['code' => 'TVA-IMPOTS',  'intitule' => 'TVA et Impôts',                'volume_horaire' => 20],
            ],
            'GEA-L2-UE03' => [
                ['code' => 'MARKET-FOND', 'intitule' => 'Marketing Fondamental',        'volume_horaire' => 20],
                ['code' => 'MARKET-OP',   'intitule' => 'Marketing Opérationnel',       'volume_horaire' => 20],
            ],
            'GEA-L2-UE04' => [
                ['code' => 'GRH-FOND',    'intitule' => 'Gestion des RH',              'volume_horaire' => 20],
                ['code' => 'PAIE-ADMIN',  'intitule' => 'Administration de la Paie',    'volume_horaire' => 20],
            ],
            'GEA-L2-UE05' => [
                ['code' => 'CONTROL-GEST','intitule' => 'Contrôle de Gestion',          'volume_horaire' => 25],
                ['code' => 'TABLEAU-BORD','intitule' => 'Tableaux de Bord',             'volume_horaire' => 25],
            ],
            'GEA-L2-UE06' => [
                ['code' => 'STAT-APPLI',  'intitule' => 'Statistiques Appliquées',      'volume_horaire' => 20],
                ['code' => 'SONDAGES',    'intitule' => 'Techniques de Sondage',        'volume_horaire' => 20],
            ],
        ];

        $count = 0;
        foreach ($ecs as $ueCode => $ecList) {
            $ue = $ues->firstWhere('code', $ueCode);
            if (!$ue) {
                $this->command->warn("UE {$ueCode} introuvable, ECs ignorés.");
                continue;
            }

            foreach ($ecList as $data) {
                Ec::firstOrCreate(
                    ['code' => $data['code']],
                    [
                        'ue_id'         => $ue->id,
                        'intitule'      => $data['intitule'],
                        'volume_horaire'=> $data['volume_horaire'],
                    ]
                );
                $count++;
            }
        }

        $this->command->info("ECs créés : {$count}");
    }
}
