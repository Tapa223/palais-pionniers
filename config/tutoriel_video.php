<?php
/*
 * Tutoriel vidéo « Comment réserver ? » (page FAQ du site).
 *
 * Pour publier la vidéo, une seule des deux options suffit :
 *   1. déposer le fichier MP4 à l'emplacement indiqué dans 'fichier'
 *      (par défaut assets/videos/comment-reserver.mp4) ;
 *   2. ou renseigner 'youtube' avec le lien ou l'identifiant d'une vidéo YouTube.
 * Tant qu'aucune vidéo n'est disponible, la page affiche un encadré
 * « Tutoriel vidéo bientôt disponible » avec les étapes de la réservation.
 */
return [
    'titre'   => 'Comment réserver un espace du Palais ?',
    'fichier' => 'assets/videos/comment-reserver.mp4',
    'affiche' => 'assets/videos/comment-reserver.jpg', // image d'aperçu facultative
    'youtube' => '',
];
