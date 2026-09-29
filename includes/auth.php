<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        if (!is_logged_in()) {
            return null;
        }

        return [
            'id'          => (int) $_SESSION['user_id'],
            'nom_complet' => $_SESSION['nom_complet'] ?? '',
            'email'       => $_SESSION['email'] ?? '',
            'role'        => $_SESSION['role'] ?? 'user',
        ];
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return in_array(
            $_SESSION['role'] ?? '',
            [
                'superadmin',
                'ministre',
                'admin_espaces',
                'admin_activites',
                'admin_messages',
                'admin_comptable'
            ],
            true
        );
    }
}

if (!function_exists('is_superadmin')) {
    function is_superadmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'superadmin';
    }
}

if (!function_exists('require_role')) {
    function require_role(
        string|array $roles,
        string $redirect = '../index.php'
    ): void {
        if (!is_logged_in()) {
            header('Location: ../login.php');
            exit;
        }

        $role = $_SESSION['role'] ?? 'user';

        $allowed = is_array($roles)
            ? $roles
            : [$roles];

        $allowed[] = 'superadmin';

        if (!in_array($role, $allowed, true)) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(
        string $redirect = '../index.php'
    ): void {
        require_role(
            [
                'admin_espaces',
                'admin_activites',
                'admin_messages',
                'admin_comptable',
                'ministre'
            ],
            $redirect
        );
    }
}

if (!function_exists('is_readonly_admin')) {
    /**
     * true si le rôle connecté n'a qu'un accès de supervision
     * sur les modules métier.
     */
    function is_readonly_admin(): bool
    {
        return in_array(
            $_SESSION['role'] ?? '',
            ['ministre'],
            true
        );
    }
}

if (!function_exists('require_client')) {
    /**
     * Bloque l'accès aux pages réservées aux clients
     * pour tout compte de service.
     */
    function require_client(
        string $redirect = 'admin/dashboard.php'
    ): void {
        if (!is_logged_in()) {
            header('Location: login.php');
            exit;
        }

        if (($_SESSION['role'] ?? 'user') !== 'user') {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

if (!function_exists('tarif_disponible')) {
    /**
     * Vérifie la disponibilité d'un tarif sur une période donnée.
     *
     * Mode créneau :
     * seules les réservations validées bloquent le créneau.
     *
     * Mode séjour :
     * les réservations validées et en attente sont prises en compte.
     */
    function tarif_disponible(
        PDO $pdo,
        int $tarifId,
        string $dateDebut,
        ?string $dateFin = null,
        ?string $heureDebut = null,
        ?string $heureFin = null,
        ?int $excludeId = null,
        int $quantiteDemandee = 1
    ): bool {

        $t = $pdo->prepare("
            SELECT quantite_disponible
            FROM tarifs
            WHERE id = ?
        ");

        $t->execute([$tarifId]);

        $qte = $t->fetchColumn();

        $limite = (
            $qte !== false
            && $qte !== null
        )
            ? (int) $qte
            : 1;

        if ($limite <= 1) {
            $quantiteDemandee =
                min($quantiteDemandee, 1);
        }

        if ($dateFin) {

            $sql = "
                SELECT COALESCE(SUM(quantite), 0)
                FROM reservations
                WHERE tarif_id = ?
                  AND statut IN ('validee', 'en_attente')
                  AND date_resa < ?
                  AND date_depart > ?
            ";

            $params = [
                $tarifId,
                $dateFin,
                $dateDebut
            ];

        } else {

            $sql = "
                SELECT COALESCE(SUM(quantite), 0)
                FROM reservations
                WHERE tarif_id = ?
                  AND date_resa = ?
                  AND statut = 'validee'
                  AND heure_debut < ?
                  AND ADDTIME(
                      heure_fin,
                      '02:00:00'
                  ) > ?
            ";

            $params = [
                $tarifId,
                $dateDebut,
                $heureFin,
                $heureDebut
            ];
        }

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $occupees =
            (int) $stmt->fetchColumn();

        return (
            $occupees
            + max(1, $quantiteDemandee)
        ) <= $limite;
    }
}

if (!function_exists('creneaux_libres_du_jour')) {
    /**
     * Calcule les plages horaires encore libres pour un tarif
     * à une date donnée.
     *
     * Plage d'ouverture : 07h00 - 22h00.
     */
    function creneaux_libres_du_jour(
        PDO $pdo,
        int $tarifId,
        string $date
    ): array {

        $stmt = $pdo->prepare("
            SELECT heure_debut, heure_fin
            FROM reservations
            WHERE tarif_id = ?
              AND date_resa = ?
              AND statut = 'validee'
            ORDER BY heure_debut ASC
        ");

        $stmt->execute([
            $tarifId,
            $date
        ]);

        $occupations =
            $stmt->fetchAll();

        $curseur =
            strtotime('07:00:00');

        $finJournee =
            strtotime('22:00:00');

        $libres = [];

        foreach ($occupations as $occupation) {

            $debutOccupe =
                strtotime(
                    $occupation['heure_debut']
                );

            $finOccupeAvecMarge =
                strtotime(
                    $occupation['heure_fin']
                ) + (2 * 3600);

            if ($debutOccupe > $curseur) {

                $libres[] =
                    date('H:i', $curseur)
                    . ' - '
                    . date('H:i', $debutOccupe);
            }

            if ($finOccupeAvecMarge > $curseur) {

                $curseur =
                    $finOccupeAvecMarge;
            }
        }

        if ($curseur < $finJournee) {

            $libres[] =
                date('H:i', $curseur)
                . ' - '
                . date('H:i', $finJournee);
        }

        return $libres;
    }
}

if (!function_exists('periode_actuelle_debut')) {
    /**
     * Premier jour de la période de bail en cours,
     * selon sa périodicité.
     */
    function periode_actuelle_debut(
        string $typeBail
    ): string {

        $mois =
            (int) date('n');

        $an =
            (int) date('Y');

        switch ($typeBail) {

            case 'trimestriel':

                $moisDebut =
                    intdiv(
                        $mois - 1,
                        3
                    ) * 3 + 1;

                break;

            case 'semestriel':

                $moisDebut =
                    intdiv(
                        $mois - 1,
                        6
                    ) * 6 + 1;

                break;

            case 'annuel':

                $moisDebut = 1;

                break;

            default:

                $moisDebut = $mois;

                break;
        }

        return sprintf(
            '%04d-%02d-01',
            $an,
            $moisDebut
        );
    }
}

if (!function_exists('limite_paiement')) {
    /**
     * Calcule la date limite de paiement à 48h.
     *
     * Si l'échéance tombe un samedi ou un dimanche,
     * elle est reportée au lundi à la même heure.
     */
    function limite_paiement(
        string $depart
    ): int {

        $limite =
            strtotime($depart)
            + (48 * 3600);

        $jourSemaine =
            (int) date('N', $limite);

        if ($jourSemaine === 6) {

            $limite =
                strtotime(
                    '+2 days',
                    $limite
                );

        } elseif ($jourSemaine === 7) {

            $limite =
                strtotime(
                    '+1 day',
                    $limite
                );
        }

        return $limite;
    }
}

if (!function_exists('annuler_reservations_concurrentes')) {
    /**
     * Appelée après l'encaissement d'une réservation validée.
     *
     * Les autres réservations validées mais non payées,
     * qui chevauchent la réservation payée, sont annulées.
     */
    function annuler_reservations_concurrentes(
        PDO $pdo,
        int $reservationPayeeId
    ): array {

        $r = $pdo->prepare("
            SELECT *
            FROM reservations
            WHERE id = ?
        ");

        $r->execute([
            $reservationPayeeId
        ]);

        $resa =
            $r->fetch();

        if (!$resa) {
            return [];
        }

        $estSejour =
            !empty($resa['date_depart']);

        if ($estSejour) {

            $sql = "
                SELECT
                    r.id,
                    r.user_id,
                    r.tarif_id,
                    r.date_resa,
                    e.nom AS espace_nom
                FROM reservations r
                JOIN espaces e
                    ON e.id = r.espace_id
                WHERE r.espace_id = ?
                  AND r.id != ?
                  AND r.statut = 'validee'
                  AND r.statut_paiement != 'paye'
                  AND NOT (
                      r.requisition_id IS NOT NULL
                      AND r.statut_paiement = 'partiellement_paye'
                  )
                  AND r.date_resa < ?
                  AND r.date_depart > ?
            ";

            $params = [
                $resa['espace_id'],
                $reservationPayeeId,
                $resa['date_depart'],
                $resa['date_resa']
            ];

        } else {

            $sql = "
                SELECT
                    r.id,
                    r.user_id,
                    r.tarif_id,
                    r.date_resa,
                    e.nom AS espace_nom
                FROM reservations r
                JOIN espaces e
                    ON e.id = r.espace_id
                WHERE r.espace_id = ?
                  AND r.id != ?
                  AND r.statut = 'validee'
                  AND r.statut_paiement != 'paye'
                  AND NOT (
                      r.requisition_id IS NOT NULL
                      AND r.statut_paiement = 'partiellement_paye'
                  )
                  AND r.date_resa = ?
                  AND r.heure_debut < ?
                  AND ADDTIME(
                      r.heure_fin,
                      '02:00:00'
                  ) > ?
            ";

            $params = [
                $resa['espace_id'],
                $reservationPayeeId,
                $resa['date_resa'],
                $resa['heure_fin'],
                $resa['heure_debut']
            ];
        }

        $stmt =
            $pdo->prepare($sql);

        $stmt->execute($params);

        $concurrentes =
            $stmt->fetchAll();

        foreach ($concurrentes as $c) {

            $pdo->prepare("
                UPDATE reservations
                SET
                    statut = 'annulee',
                    notification_vue = 0
                WHERE id = ?
            ")->execute([
                $c['id']
            ]);

            $creneaux =
                !$estSejour
                    ? creneaux_libres_du_jour(
                        $pdo,
                        (int) $c['tarif_id'],
                        $c['date_resa']
                    )
                    : [];

            notify(
                '',
                'reservation_impossible',
                "La salle « {$c['espace_nom']} » n'est plus disponible pour votre créneau demandé — une autre personne a réglé son paiement en premier."
                . (
                    $creneaux
                        ? ' Créneaux encore libres ce jour : '
                            . implode(', ', $creneaux)
                            . '.'
                        : ' Contactez-nous pour connaître les prochaines disponibilités.'
                ),
                'mon-compte.php',
                (int) $c['user_id']
            );

            log_activity(
                'reservation_annulee_conflit',
                'reservations',
                "Réservation #{$c['id']} annulée automatiquement — paiement concurrent #{$reservationPayeeId} effectué en premier"
            );
        }

        return $concurrentes;
    }
}

if (!function_exists('admin_nav')) {
    function admin_nav(): array
    {
        $role =
            $_SESSION['role'] ?? 'user';

        $all = [

            'dashboard.php' => [
                'fas fa-chart-pie',
                'Tableau de bord',
                [
                    'superadmin',
                    'ministre',
                    'admin_espaces',
                    'admin_activites',
                    'admin_messages',
                    'admin_comptable'
                ]
            ],

            'notifications.php' => [
                'fas fa-bell',
                'Notifications',
                [
                    'superadmin',
                    'ministre',
                    'admin_espaces',
                    'admin_activites',
                    'admin_messages',
                    'admin_comptable'
                ]
            ],

            'espaces.php' => [
                'fas fa-building',
                'Espaces',
                [
                    'superadmin',
                    'ministre',
                    'admin_espaces'
                ]
            ],

            'reservations.php' => [
                'fas fa-calendar-check',
                'Réservations',
                [
                    'superadmin',
                    'ministre',
                    'admin_espaces',
                    'admin_comptable'
                ]
            ],

            'paiements.php' => [
                'fas fa-cash-register',
                'Paiements',
                [
                    'superadmin',
                    'ministre',
                    'admin_comptable'
                ]
            ],

            'acomptes.php' => [
                'fas fa-coins',
                'Acomptes',
                [
                    'superadmin',
                    'ministre',
                    'admin_comptable'
                ]
            ],

            'requisitions.php' => [
                'fas fa-landmark',
                'Réquisitions',
                [
                    'superadmin',
                    'ministre',
                    'admin_comptable'
                ]
            ],

            'baux.php' => [
                'fas fa-file-signature',
                'Baux',
                [
                    'superadmin',
                    'ministre',
                    'admin_comptable',
                    'admin_espaces'
                ]
            ],

            'demandes-bail.php' => [
                'fas fa-inbox',
                'Demandes de bail',
                [
                    'superadmin',
                    'ministre',
                    'admin_comptable',
                    'admin_espaces'
                ]
            ],

            'demandes-services.php' => [
                'fas fa-concierge-bell',
                'Demandes de services',
                [
                    'superadmin',
                    'ministre',
                    'admin_comptable',
                    'admin_espaces'
                ]
            ],

            'jeunes-engages.php' => [
                'fas fa-hand-fist',
                'Jeunes engagés',
                [
                    'superadmin',
                    'ministre',
                    'admin_activites'
                ]
            ],

            'guichet.php' => [
                'fas fa-store',
                'Guichet',
                [
                    'superadmin',
                    'admin_espaces',
                    'admin_comptable'
                ]
            ],

            'activites.php' => [
                'fas fa-star',
                'Activités',
                [
                    'superadmin',
                    'ministre',
                    'admin_activites'
                ]
            ],

            'personnalites.php' => [
                'fas fa-user-tie',
                'Personnalités',
                [
                    'superadmin',
                    'ministre',
                    'admin_activites'
                ]
            ],

            'direction.php' => [
                'fas fa-user-shield',
                'Direction',
                [
                    'superadmin',
                    'ministre'
                ]
            ],

            'formations.php' => [
                'fas fa-graduation-cap',
                'Formations',
                [
                    'superadmin',
                    'ministre',
                    'admin_activites'
                ]
            ],

            'personnel.php' => [
                'fas fa-id-badge',
                'Personnel',
                [
                    'superadmin',
                    'ministre',
                    'admin_activites'
                ]
            ],

            'messages.php' => [
                'fas fa-envelope',
                'Messages',
                [
                    'superadmin',
                    'ministre',
                    'admin_messages',
                    'admin_espaces',
                    'admin_activites'
                ]
            ],

            'observations.php' => [
                'fas fa-eye',
                'Observations',
                [
                    'superadmin',
                    'ministre',
                    'admin_espaces',
                    'admin_activites',
                    'admin_messages',
                    'admin_comptable'
                ]
            ],

            'activity.php' => [
                'fas fa-history',
                'Journal',
                [
                    'superadmin',
                    'ministre'
                ]
            ],

            'users.php' => [
                'fas fa-users',
                'Utilisateurs',
                [
                    'superadmin',
                    'ministre'
                ]
            ]
        ];

        $nav = [];

        foreach (
            $all
            as $href => [$icon, $label, $roles]
        ) {

            if (
                in_array(
                    $role,
                    $roles,
                    true
                )
            ) {
                $nav[$href] = [
                    $icon,
                    $label
                ];
            }
        }

        return $nav;
    }
}

if (!function_exists('admin_nav_badges')) {
    /**
     * Compteurs contextuels affichés dans la sidebar.
     *
     * Le badge Réquisitions correspond exactement
     * à l'onglet "Choix fait, à traiter".
     */
    function admin_nav_badges(): array
    {
        $badges = [];

        try {

            $pdo = db();

            $badges['reservations.php'] =
                (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM reservations
                    WHERE statut = 'en_attente'
                ")->fetchColumn();

            $badges['paiements.php'] =
                (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM reservations
                    WHERE statut = 'validee'
                      AND statut_paiement != 'paye'
                ")->fetchColumn();

            $badges['acomptes.php'] =
                (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM reservations
                    WHERE statut_paiement =
                        'partiellement_paye'
                ")->fetchColumn();

            /*
             * Une réquisition est considérée comme
             * "à traiter" dès que le client a effectué
             * son choix.
             *
             * Les deux statuts sont volontairement
             * pris en compte :
             *
             * choix_recu
             * en_traitement
             *
             * Cette condition est identique à celle
             * utilisée dans admin/requisitions.php.
             */
            $badges['requisitions.php'] =
                (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM requisitions_ministerielles
                    WHERE statut IN (
                        'choix_recu',
                        'en_traitement'
                    )
                    AND choix_client IS NOT NULL
                ")->fetchColumn();

            $stmtBaux =
                $pdo->prepare("
                    SELECT COUNT(*)
                    FROM espaces e
                    WHERE e.gerant_externe IS NOT NULL
                      AND e.gerant_externe != ''
                      AND NOT EXISTS (
                          SELECT 1
                          FROM bail_paiements bp
                          WHERE bp.espace_id = e.id
                            AND bp.mois = ?
                      )
                ");

            $stmtBaux->execute([
                date('Y-m-01')
            ]);

            $badges['baux.php'] =
                (int) $stmtBaux->fetchColumn();

            $badges['demandes-bail.php'] =
                (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM demandes_bail
                    WHERE statut = 'en_attente'
                ")->fetchColumn();

        } catch (Exception $e) {

            error_log(
                'admin_nav_badges(): '
                . $e->getMessage()
            );
        }

        return $badges;
    }
}

if (!function_exists('e')) {
    function e(?string $s): string
    {
        return htmlspecialchars(
            (string) $s,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('ref_recu')) {
    /**
     * Référence lisible et unique d'un reçu de paiement.
     */
    function ref_recu(
        int $paiementId
    ): string {

        return date('y')
            . '-'
            . str_pad(
                (string) $paiementId,
                3,
                '0',
                STR_PAD_LEFT
            )
            . '/DGPP-C';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (
            empty(
                $_SESSION['csrf_token']
            )
        ) {
            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(
        ?string $token
    ): bool {

        if (
            empty(
                $_SESSION['csrf_token']
            )
            || empty($token)
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
    }
}

if (!function_exists('log_activity')) {
    function log_activity(
        string $action,
        string $module,
        string $details = ''
    ): void {

        try {

            $u =
                current_user();

            if (!$u) {
                return;
            }

            db()->prepare("
                INSERT INTO activity_log
                    (
                        user_id,
                        user_nom,
                        action,
                        module,
                        details
                    )
                VALUES
                    (?, ?, ?, ?, ?)
            ")->execute([
                $u['id'],
                $u['nom_complet'],
                $action,
                $module,
                $details
            ]);

        } catch (Exception $e) {

            error_log(
                'log_activity(): '
                . $e->getMessage()
            );
        }
    }
}

if (!function_exists('notify')) {
    function notify(
        string $role,
        string $type,
        string $message,
        string $lien = '',
        ?int $destinataireId = null
    ): void {

        try {

            db()->prepare("
                INSERT INTO notifications
                    (
                        destinataire_role,
                        destinataire_id,
                        type,
                        message,
                        lien
                    )
                VALUES
                    (?, ?, ?, ?, ?)
            ")->execute([
                $role,
                $destinataireId,
                $type,
                $message,
                $lien
            ]);

        } catch (Exception $e) {

            error_log(
                'notify(): '
                . $e->getMessage()
            );
        }
    }
}

if (!function_exists('count_notifications')) {
    function count_notifications(): int
    {
        $role =
            $_SESSION['role'] ?? '';

        $uid =
            (int) (
                $_SESSION['user_id'] ?? 0
            );

        try {

            $stmt =
                db()->prepare("
                    SELECT COUNT(*)
                    FROM notifications
                    WHERE lu = 0
                      AND (
                          destinataire_role = ?
                          OR destinataire_id = ?
                      )
                ");

            $stmt->execute([
                $role,
                $uid
            ]);

            return (int)
                $stmt->fetchColumn();

        } catch (Exception $e) {

            error_log(
                'count_notifications(): '
                . $e->getMessage()
            );

            return 0;
        }
    }
}

if (!function_exists('expirer_reservations_non_payees')) {
    /**
     * Annule automatiquement les réservations validées mais
     * toujours impayées 48h après leur validation.
     */
    function expirer_reservations_non_payees(): void
    {
        $pdo = db();

        try {

            $stmt = $pdo->query("
                SELECT
                    r.id,
                    r.user_id,
                    r.date_resa,
                    r.date_validation,
                    e.nom AS espace_nom
                FROM reservations r
                JOIN espaces e
                    ON e.id = r.espace_id
                WHERE r.statut = 'validee'
                  AND r.statut_paiement != 'paye'
                  AND r.date_validation IS NOT NULL
                  /*
                   * Une réservation issue d'une réquisition qui porte déjà
                   * des paiements transférés (solde restant à régler) n'est
                   * jamais annulée automatiquement : l'argent encaissé
                   * resterait sinon rattaché à une réservation expirée.
                   */
                  AND NOT (
                      r.requisition_id IS NOT NULL
                      AND r.statut_paiement = 'partiellement_paye'
                  )
            ");

            $candidates =
                $stmt->fetchAll();

            $expirees =
                array_filter(
                    $candidates,
                    fn($r) =>
                        limite_paiement(
                            $r['date_validation']
                        ) < time()
                );

        } catch (Exception $e) {

            error_log(
                'expirer_reservations_non_payees(): '
                . $e->getMessage()
            );

            return;
        }

        if (!$expirees) {
            return;
        }

        foreach ($expirees as $r) {

            $pdo->prepare("
                UPDATE reservations
                SET
                    statut = 'expiree',
                    note_admin = CONCAT(
                        COALESCE(note_admin, ''),
                        '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.'
                    )
                WHERE id = ?
            ")->execute([
                $r['id']
            ]);

            notify(
                'admin_espaces',
                'reservation_expiree',
                "Réservation #{$r['id']} pour « {$r['espace_nom']} » annulée automatiquement (48h sans paiement)",
                'reservations.php'
            );

            notify(
                '',
                'reservation_expiree',
                "Votre demande pour « {$r['espace_nom']} » du "
                . date(
                    'd/m/Y',
                    strtotime($r['date_resa'])
                )
                . " a été annulée automatiquement : le paiement n'a pas été effectué dans le délai de 48h. Vous pouvez faire une nouvelle demande si le créneau vous intéresse toujours.",
                'espaces.php',
                (int) $r['user_id']
            );

            log_activity(
                'reservation_expiree',
                'reservations',
                "Réservation #{$r['id']} annulée automatiquement (délai 48h dépassé)"
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| RÉQUISITIONS → NOUVELLE RÉSERVATION NORMALE
|--------------------------------------------------------------------------
| Fonctions partagées par reserver.php, traitement-reservation.php,
| mon-compte.php, admin/reservations.php et admin/requisition-detail.php.
| Elles ne créent aucun circuit parallèle : la nouvelle réservation reste
| une réservation normale (en_attente → validation admin_espaces → suite
| normale), simplement rattachée à sa réquisition via
| reservations.requisition_id.
*/

if (!function_exists('horaire_reservation_erreur')) {
    /**
     * Contrôle serveur des horaires d'une réservation en mode créneau.
     *
     * Reprend exactement les règles des listes de reserver.php :
     * format HH:MM, pas de 30 minutes, début de 07:00 à 21:30,
     * fin de 08:00 à 22:30, fin strictement après le début.
     *
     * Retourne null si les horaires sont valides, sinon un message.
     */
    function horaire_reservation_erreur(
        string $debut,
        string $fin
    ): ?string {

        $format = '/^(?:[01]\d|2[0-3]):(?:00|30)$/';

        if (
            !preg_match($format, $debut)
            || !preg_match($format, $fin)
        ) {
            return "Horaires invalides : merci de choisir l'heure de début et de fin dans les listes proposées (par tranches de 30 minutes).";
        }

        if ($debut < '07:00' || $debut > '21:30') {
            return "L'heure de début doit être comprise entre 07:00 et 21:30.";
        }

        if ($fin < '08:00' || $fin > '22:30') {
            return "L'heure de fin doit être comprise entre 08:00 et 22:30.";
        }

        if ($fin <= $debut) {
            return "L'heure de fin doit être après l'heure de début.";
        }

        return null;
    }
}

if (!function_exists('montant_attendu_reservation')) {
    /**
     * Montant attendu d'une réservation, calculé à partir de son tarif
     * et de son espace.
     *
     * Formule strictement identique à celle déjà utilisée dans
     * admin/paiements.php et generer_bon.php :
     * tarif × nuitées × quantité (+ petit-déjeuner 5 000 / nuit / chambre
     * en séjour, + supplément VIP en créneau).
     *
     * Retourne 0 si le tarif n'est plus connu (même comportement que
     * admin/paiements.php, qui ne fixe alors aucun montant de référence).
     */
    function montant_attendu_reservation(
        PDO $pdo,
        int $reservationId
    ): float {

        $stmt = $pdo->prepare("
            SELECT
                r.heure_debut,
                r.date_resa,
                r.date_depart,
                r.quantite,
                r.petit_dejeuner,
                r.vip,
                t.montant AS tarif_montant,
                e.prix_vip
            FROM reservations r
            JOIN espaces e
                ON e.id = r.espace_id
            LEFT JOIN tarifs t
                ON t.id = r.tarif_id
            WHERE r.id = ?
        ");

        $stmt->execute([$reservationId]);

        $r = $stmt->fetch();

        if (!$r) {
            return 0.0;
        }

        $estSejour = empty($r['heure_debut']);

        $nuitees = $estSejour
            ? max(
                1,
                (int) (
                    (strtotime((string) $r['date_depart']) - strtotime((string) $r['date_resa']))
                    / 86400
                )
            )
            : 1;

        $quantite = max(1, (int) ($r['quantite'] ?? 1));

        $montant = $r['tarif_montant']
            ? ((float) $r['tarif_montant'] * $nuitees * $quantite)
            : 0.0;

        if ($estSejour && $r['petit_dejeuner']) {
            $montant += 5000 * $nuitees * $quantite;
        }

        if (!$estSejour && !empty($r['vip'])) {
            $montant += (float) ($r['prix_vip'] ?? 0);
        }

        return round($montant, 2);
    }
}

if (!function_exists('reservation_requisition_active')) {
    /**
     * Nouvelle réservation encore active rattachée à une réquisition.
     *
     * Une réservation refusée, annulée ou expirée ne compte plus :
     * le client peut alors refaire une demande dans le cadre de la
     * même réquisition.
     */
    function reservation_requisition_active(
        PDO $pdo,
        int $requisitionId,
        bool $verrouiller = false
    ): ?array {

        $stmt = $pdo->prepare("
            SELECT
                id,
                statut,
                statut_paiement,
                espace_id,
                date_resa,
                date_depart,
                heure_debut,
                heure_fin
            FROM reservations
            WHERE requisition_id = ?
              AND statut IN ('en_attente', 'validee', 'requisitionnee')
            ORDER BY id DESC
            LIMIT 1
        " . ($verrouiller ? ' FOR UPDATE' : ''));

        $stmt->execute([$requisitionId]);

        $row = $stmt->fetch();

        return $row ?: null;
    }
}

if (!function_exists('requisition_contexte_nouvelle_reservation')) {
    /**
     * Vérifie qu'un client peut créer une nouvelle réservation dans le
     * cadre d'une réquisition, et retourne le contexte de la réservation
     * d'origine.
     *
     * Contrôles :
     * - la réquisition existe et la réservation d'origine appartient
     *   au client connecté ;
     * - le choix du client est « nouvelle_date » ou « autre_espace » ;
     * - la réquisition est encore ouverte (choix_recu / en_traitement) ;
     * - la réservation d'origine est bien « requisitionnee » ;
     * - aucune nouvelle réservation active n'existe déjà.
     *
     * Avec $verrouiller = true, les lignes sont verrouillées (FOR UPDATE) :
     * à appeler dans une transaction.
     *
     * @return array{ok: bool, erreur: ?string, code: ?string, req: ?array}
     */
    function requisition_contexte_nouvelle_reservation(
        PDO $pdo,
        int $requisitionId,
        int $userId,
        bool $verrouiller = false
    ): array {

        $stmt = $pdo->prepare("
            SELECT
                rm.id               AS requisition_id,
                rm.reservation_id   AS reservation_origine_id,
                rm.choix_client,
                rm.details_choix,
                rm.statut           AS requisition_statut,
                r.user_id,
                r.espace_id,
                r.tarif_id,
                r.date_resa,
                r.date_depart,
                r.heure_debut,
                r.heure_fin,
                r.quantite,
                r.petit_dejeuner,
                r.vip,
                r.motif,
                r.statut            AS reservation_statut,
                e.nom               AS espace_nom,
                e.mode_reservation
            FROM requisitions_ministerielles rm
            JOIN reservations r
                ON r.id = rm.reservation_id
            JOIN espaces e
                ON e.id = r.espace_id
            WHERE rm.id = ?
              AND r.user_id = ?
            LIMIT 1
        " . ($verrouiller ? ' FOR UPDATE' : ''));

        $stmt->execute([$requisitionId, $userId]);

        $req = $stmt->fetch();

        if (!$req) {
            return [
                'ok'     => false,
                'erreur' => "Cette réquisition est introuvable ou ne vous appartient pas.",
                'code'   => 'introuvable',
                'req'    => null,
            ];
        }

        if (!in_array($req['choix_client'], ['nouvelle_date', 'autre_espace'], true)) {
            return [
                'ok'     => false,
                'erreur' => "Cette réquisition ne concerne pas une nouvelle date ou un autre espace.",
                'code'   => 'choix',
                'req'    => $req,
            ];
        }

        if (
            !in_array($req['requisition_statut'], ['choix_recu', 'en_traitement'], true)
            || $req['reservation_statut'] !== 'requisitionnee'
        ) {
            return [
                'ok'     => false,
                'erreur' => "Cette réquisition n'est plus ouverte à une nouvelle réservation.",
                'code'   => 'fermee',
                'req'    => $req,
            ];
        }

        $active = reservation_requisition_active(
            $pdo,
            $requisitionId,
            $verrouiller
        );

        if ($active) {
            return [
                'ok'     => false,
                'erreur' => "Une nouvelle réservation (#{$active['id']}) est déjà en cours pour cette réquisition.",
                'code'   => 'active',
                'req'    => $req,
            ];
        }

        $req['nuits'] = !empty($req['date_depart'])
            ? max(
                1,
                (int) (
                    (strtotime($req['date_depart']) - strtotime($req['date_resa']))
                    / 86400
                )
            )
            : 0;

        return [
            'ok'     => true,
            'erreur' => null,
            'code'   => null,
            'req'    => $req,
        ];
    }
}

if (!function_exists('creneau_occupe_par_reservation_payee')) {
    /**
     * Retourne l'identifiant d'une réservation validée ET déjà (totalement
     * ou partiellement) payée qui occupe le créneau demandé sur le même
     * espace, sinon null.
     *
     * Même règle de chevauchement que tarif_disponible() (marge de 2h
     * après la fin de la réservation existante), appliquée à l'espace
     * comme annuler_reservations_concurrentes().
     *
     * Utilisée uniquement pour les réservations issues d'une réquisition :
     * un paiement déjà encaissé ne doit jamais être rattaché à un créneau
     * qu'un autre client a déjà définitivement réglé.
     */
    function creneau_occupe_par_reservation_payee(
        PDO $pdo,
        int $espaceId,
        string $date,
        string $heureDebut,
        string $heureFin,
        ?int $excludeId = null
    ): ?int {

        $sql = "
            SELECT id
            FROM reservations
            WHERE espace_id = ?
              AND date_resa = ?
              AND statut = 'validee'
              AND statut_paiement IN ('paye', 'partiellement_paye')
              AND heure_debut IS NOT NULL
              AND heure_debut < ?
              AND ADDTIME(heure_fin, '02:00:00') > ?
        ";

        $params = [
            $espaceId,
            $date,
            $heureFin,
            $heureDebut,
        ];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }
}

if (!function_exists('chevauche_reservation_origine')) {
    /**
     * Vrai si la période demandée recouvre le créneau (ou le séjour)
     * réquisitionné de la réservation d'origine, sur le même espace.
     *
     * Une réservation « requisitionnee » n'est plus comptée comme une
     * occupation normale : sans ce contrôle, le client pourrait
     * reprendre exactement le créneau que l'institution occupe.
     */
    function chevauche_reservation_origine(
        array $origine,
        int $espaceId,
        string $dateResa,
        ?string $dateDepart,
        ?string $heureDebut,
        ?string $heureFin
    ): bool {

        if ((int) $origine['espace_id'] !== $espaceId) {
            return false;
        }

        if (!empty($origine['date_depart'])) {

            if (!$dateDepart) {
                return false;
            }

            return $dateResa < $origine['date_depart']
                && $dateDepart > $origine['date_resa'];
        }

        if (
            $dateResa !== $origine['date_resa']
            || !$heureDebut
            || !$heureFin
            || empty($origine['heure_debut'])
            || empty($origine['heure_fin'])
        ) {
            return false;
        }

        $debutOrigine = strtotime($origine['heure_debut']);
        $finOrigineMarge = strtotime($origine['heure_fin']) + 2 * 3600;

        return $debutOrigine < strtotime($heureFin)
            && $finOrigineMarge > strtotime($heureDebut);
    }
}

if (!function_exists('transferer_paiements_requisition')) {
    /**
     * Rattache à la nouvelle réservation les paiements déjà encaissés sur
     * la réservation d'origine d'une réquisition.
     *
     * - aucun paiement n'est créé : les lignes existantes de `paiements`
     *   changent seulement de reservation_id (montant, date, mode,
     *   référence, agent et numéro de reçu restent identiques) ;
     * - chaque ligne transférée reçoit une trace dans `paiements.note` ;
     * - le statut de paiement de la nouvelle réservation est recalculé à
     *   partir du montant attendu de SON tarif ;
     * - l'ancienne réservation garde son statut « requisitionnee », son
     *   statut de paiement repasse à « non_paye » (elle ne porte plus
     *   aucun encaissement) et reçoit une trace dans note_admin ;
     * - un éventuel trop-perçu est conservé dans
     *   operations_requisition.montant_concerne pour être traité par le
     *   comptable via le remboursement existant.
     *
     * Idempotent : un second appel ne trouve plus de paiement sur la
     * réservation d'origine et ne modifie rien.
     *
     * DOIT être appelée à l'intérieur d'une transaction.
     */
    function transferer_paiements_requisition(
        PDO $pdo,
        int $nouvelleReservationId
    ): array {

        $resultat = [
            'transfere'   => 0.0,
            'nb'          => 0,
            'du'          => 0.0,
            'total'       => 0.0,
            'statut'      => null,
            'solde'       => 0.0,
            'trop_percu'  => 0.0,
            'origine_id'  => null,
            'requisition_id' => null,
        ];

        $stmt = $pdo->prepare("
            SELECT id, requisition_id
            FROM reservations
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$nouvelleReservationId]);
        $nouvelle = $stmt->fetch();

        if (!$nouvelle || empty($nouvelle['requisition_id'])) {
            return $resultat;
        }

        $requisitionId = (int) $nouvelle['requisition_id'];

        $stmt = $pdo->prepare("
            SELECT reservation_id
            FROM requisitions_ministerielles
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$requisitionId]);
        $origineId = (int) $stmt->fetchColumn();

        $resultat['origine_id'] = $origineId ?: null;
        $resultat['requisition_id'] = $requisitionId;

        if (!$origineId || $origineId === $nouvelleReservationId) {
            return $resultat;
        }

        $stmt = $pdo->prepare("
            SELECT id, montant
            FROM paiements
            WHERE reservation_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$origineId]);
        $paiementsOrigine = $stmt->fetchAll();

        if (!$paiementsOrigine) {
            return $resultat;
        }

        $transfere = 0.0;

        foreach ($paiementsOrigine as $p) {
            $transfere += (float) $p['montant'];
        }

        $trace = '[Réquisition #' . $requisitionId . '] Paiement transféré de la réservation #'
            . $origineId . ' vers la réservation #' . $nouvelleReservationId
            . ' le ' . date('d/m/Y à H:i') . '.';

        $pdo->prepare("
            UPDATE paiements
            SET
                reservation_id = ?,
                note = CONCAT(
                    COALESCE(note, ''),
                    CASE WHEN note IS NULL OR note = '' THEN '' ELSE '\n' END,
                    ?
                )
            WHERE reservation_id = ?
        ")->execute([
            $nouvelleReservationId,
            $trace,
            $origineId,
        ]);

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0)
            FROM paiements
            WHERE reservation_id = ?
        ");
        $stmt->execute([$nouvelleReservationId]);
        $total = (float) $stmt->fetchColumn();

        $du = montant_attendu_reservation($pdo, $nouvelleReservationId);

        /*
         * Même règle que admin/paiements.php : sans montant de référence
         * connu, la réservation est considérée comme payée.
         */
        $statut = ($du > 0 && $total < $du)
            ? 'partiellement_paye'
            : 'paye';

        $solde = $du > 0 ? max(0.0, $du - $total) : 0.0;
        $tropPercu = $du > 0 ? max(0.0, $total - $du) : 0.0;

        $pdo->prepare("
            UPDATE reservations
            SET
                statut_paiement = ?,
                date_limite_solde = NULL,
                paiement_notifie = 0
            WHERE id = ?
        ")->execute([
            $statut,
            $nouvelleReservationId,
        ]);

        $pdo->prepare("
            UPDATE reservations
            SET
                statut_paiement = 'non_paye',
                date_limite_solde = NULL,
                note_admin = CONCAT(
                    COALESCE(note_admin, ''),
                    CASE WHEN note_admin IS NULL OR note_admin = '' THEN '' ELSE '\n' END,
                    ?
                )
            WHERE id = ?
        ")->execute([
            '[Réquisition #' . $requisitionId . '] '
                . number_format($transfere, 0, ',', ' ')
                . ' FCFA encaissés transférés vers la nouvelle réservation #'
                . $nouvelleReservationId . ' le ' . date('d/m/Y à H:i') . '.',
            $origineId,
        ]);

        $description = 'Paiements transférés vers la réservation #' . $nouvelleReservationId
            . ' : ' . number_format($transfere, 0, ',', ' ') . ' FCFA'
            . ' — montant attendu : ' . number_format($du, 0, ',', ' ') . ' FCFA'
            . ($solde > 0 ? ' — solde à régler : ' . number_format($solde, 0, ',', ' ') . ' FCFA' : '')
            . ($tropPercu > 0 ? ' — trop-perçu à rembourser : ' . number_format($tropPercu, 0, ',', ' ') . ' FCFA' : '')
            . '.';

        $stmt = $pdo->prepare("
            SELECT id
            FROM operations_requisition
            WHERE requisition_id = ?
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$requisitionId]);
        $operationId = (int) $stmt->fetchColumn();

        if ($operationId > 0) {
            $pdo->prepare("
                UPDATE operations_requisition
                SET
                    montant_concerne = CASE WHEN ? > 0 THEN ? ELSE montant_concerne END,
                    description = CONCAT(
                        COALESCE(description, ''),
                        CASE WHEN description IS NULL OR description = '' THEN '' ELSE '\n' END,
                        ?
                    )
                WHERE id = ?
            ")->execute([
                $tropPercu,
                $tropPercu,
                $description,
                $operationId,
            ]);
        }

        log_activity(
            'requisition_paiement_transfere',
            'reservations',
            $description . ' (réquisition #' . $requisitionId . ', réservation d\'origine #' . $origineId . ')'
        );

        $resultat['transfere'] = $transfere;
        $resultat['nb'] = count($paiementsOrigine);
        $resultat['du'] = $du;
        $resultat['total'] = $total;
        $resultat['statut'] = $statut;
        $resultat['solde'] = $solde;
        $resultat['trop_percu'] = $tropPercu;

        return $resultat;
    }
}

if (!function_exists('requisition_suivi_nouvelle_reservation')) {
    /**
     * État de la nouvelle réservation rattachée à une réquisition
     * (reservations.requisition_id) et du trop-perçu éventuel issu du
     * transfert des paiements lors de sa validation.
     */
    function requisition_suivi_nouvelle_reservation(PDO $pdo, int $requisitionId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                n.id,
                n.statut,
                n.statut_paiement,
                n.date_resa,
                n.date_depart,
                n.heure_debut,
                n.heure_fin,
                e.nom AS espace_nom,
                (
                    SELECT COALESCE(SUM(p.montant), 0)
                    FROM paiements p
                    WHERE p.reservation_id = n.id
                ) AS total_paye
            FROM reservations n
            JOIN espaces e ON e.id = n.espace_id
            WHERE n.requisition_id = ?
            ORDER BY n.id DESC
        ");
        $stmt->execute([$requisitionId]);
        $liste = $stmt->fetchAll();

        $validee = null;
        foreach ($liste as $n) {
            if (in_array($n['statut'], ['validee', 'requisitionnee'], true)) {
                $validee = $n;
                break;
            }
        }

        $stmt = $pdo->prepare("
            SELECT montant_concerne
            FROM operations_requisition
            WHERE requisition_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$requisitionId]);
        $tropPercu = $validee ? (float)$stmt->fetchColumn() : 0.0;

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM remboursements
            WHERE requisition_id = ?
              AND resultat = 'effectue'
        ");
        $stmt->execute([$requisitionId]);
        $rembourse = (int)$stmt->fetchColumn() > 0;

        return [
            'liste'      => $liste,
            'validee'    => $validee,
            'trop_percu' => $tropPercu,
            'rembourse'  => $rembourse,
            'a_rembourser' => $validee !== null && $tropPercu > 0 && !$rembourse,
        ];
    }
}
