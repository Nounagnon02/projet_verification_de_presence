<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fenêtre de prise de présence
    |--------------------------------------------------------------------------
    |
    | La fenêtre est ancrée sur l'HEURE DE FIN du cours, et non sur son heure de
    | début. Un scan accepté dès le début de séance permettrait à un étudiant de
    | valider sa présence puis de repartir — c'est la fraude par « absence
    | partielle » que le système est censé empêcher.
    |
    | Ces bornes sont lues par Evenement::ouvertureScan() et
    | Evenement::fermetureScan(), seuls points de calcul de la fenêtre. Aucun
    | contrôleur ni commande ne doit redéfinir ces durées localement.
    |
    */

    'scan' => [
        'minutes_avant_fin' => (int) env('PRESENCE_SCAN_AVANT_FIN', 15),
        'minutes_apres_fin' => (int) env('PRESENCE_SCAN_APRES_FIN', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code dynamique
    |--------------------------------------------------------------------------
    |
    | Durée de vie d'un token avant rotation. C'est la valeur sur laquelle repose
    | l'argument anti-fraude du système : un code photographié puis partagé est
    | déjà périmé quand le destinataire le scanne.
    |
    | Le token n'est PAS à usage unique. Il l'a été, et la conséquence, mesurée,
    | était qu'un seul étudiant pouvait valider par token : les suivants
    | recevaient 410 jusqu'à la rotation suivante, soit au mieux un étudiant par
    | minute. Ce qui rend un code partagé inexploitable est cette durée de vie,
    | pas le nombre de fois qu'il sert. La protection contre la double validation
    | est ailleurs : contrainte d'unicité (etudiant_id, evenement_id).
    |
    | La rotation effective dépend du planificateur, qui tourne chaque minute.
    | Descendre nettement sous 60 secondes n'aurait donc pas d'effet réel sans
    | changer la fréquence du cron.
    |
    */

    'qr' => [
        'ttl_secondes' => (int) env('PRESENCE_QR_TTL', 60),

        /*
        | Combien de minutes avant la fin du cours l'étudiant responsable voit
        | le QR Code dans l'application mobile.
        |
        | La consigne de l'encadrement est de dix minutes. À noter que la fenêtre
        | de scan, elle, ouvre quinze minutes avant la fin : pendant ces cinq
        | minutes d'écart, seule l'interface d'administration affiche le code.
        | Porter cette valeur à 15 aligne les deux.
        */
        'visible_delegue_avant_fin' => (int) env('PRESENCE_QR_DELEGUE_AVANT_FIN', 10),
    ],


    /*
    |--------------------------------------------------------------------------
    | Durée d'une séance
    |--------------------------------------------------------------------------
    |
    | Plafond de durée d'un créneau unique. Il est distinct du volume horaire de
    | l'EC : ce dernier borne le TOTAL des séances, celui-ci borne CHACUNE. Sans
    | lui, un cours disposant de dix-huit heures restantes acceptait un créneau
    | de huit heures à vingt-trois heures — conforme au volume, absurde en salle.
    |
    | Plusieurs séances d'un même cours dans une journée restent possibles : le
    | plafond porte sur la durée d'un créneau, pas sur leur nombre.
    |
    */

    'seance' => [
        'duree_max_heures' => (float) env('PRESENCE_SEANCE_DUREE_MAX', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites de débit du scan (CDC 9.2.4)
    |--------------------------------------------------------------------------
    |
    | Par étudiant : trois scans par minute. C'est la règle qui compte — le
    | scan est authentifié, l'appelant est donc garanti par le serveur.
    |
    | Par adresse IP : un plafond LARGE, un filet contre l'inondation, pas une
    | règle anti-fraude. Il a été fixé à trois par minute, comme la limite par
    | étudiant, et c'était un défaut : une salle, un campus derrière un même NAT
    | ou un opérateur mobile (CGNAT) font apparaître des dizaines, voire des
    | centaines d'étudiants sous une seule adresse — le cas nominal du produit,
    | puisque la vérification de réseau attend justement des étudiants sur le
    | même réseau que la salle. À trois par minute, 497 étudiants sur 500 se
    | seraient vu répondre 429.
    |
    | Cette clé ne protège d'ailleurs pas du bourrage d'identifiants :
    | l'authentification (auth:sanctum) s'exécute AVANT le limiteur, un jeton
    | invalide est refusé en 401 sans le compter. Elle doit rester très au-dessus
    | de l'hypothèse H3 (500 scans simultanés) : à 600, une rafale de 500 étudiants
    | suivie de quelques retardataires dans la même minute suffisait à refuser des
    | scans légitimes — mesuré en enchaînant deux campagnes. Le serveur absorbe
    | de l'ordre de 300 requêtes par seconde : 2 000 par minute n'est un filet
    | que contre un flot anormal.
    |
    */

    'limites' => [
        'scan_par_etudiant' => (int) env('PRESENCE_SCAN_MAX_PAR_ETUDIANT', 3),
        'scan_par_ip'       => (int) env('PRESENCE_SCAN_MAX_PAR_IP', 2000),
    ],

];
