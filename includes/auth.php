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

        // Espace client : clients classiques et comptes partenaires
        // (le partenaire réserve par le circuit normal, sans droit d'administration)
        if (!in_array($_SESSION['role'] ?? 'user', ['user', 'partenaire'], true)) {
            header('Location: ' . $redirect);
            exit;
        }

        // Compte partenaire désactivé en cours de session : déconnexion
        if (($_SESSION['role'] ?? '') === 'partenaire') {
            $pdoC = db();
            $actif = $pdoC->prepare("SELECT actif FROM users WHERE id = ?");
            $actif->execute([(int)$_SESSION['user_id']]);
            if (!(int)$actif->fetchColumn() || !partenaire_utilisateur($pdoC, (int)$_SESSION['user_id'])) {
                $_SESSION = [];
                session_destroy();
                header('Location: login.php');
                exit;
            }
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
                  -- une réservation qui a déjà reçu un paiement (acompte)
                  -- conserve son créneau : elle n'est jamais annulée ici
                  AND NOT EXISTS (
                      SELECT 1 FROM paiements p WHERE p.reservation_id = r.id
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
                  -- une réservation qui a déjà reçu un paiement (acompte)
                  -- conserve son créneau : elle n'est jamais annulée ici
                  AND NOT EXISTS (
                      SELECT 1 FROM paiements p WHERE p.reservation_id = r.id
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

            'partenaires.php' => [
                'fas fa-handshake',
                'Partenaires',
                [
                    'superadmin',
                    'ministre',
                    'admin_comptable',
                    'admin_espaces'
                ]
            ],

            'suggestions.php' => [
                'fas fa-comment-dots',
                'Suggestions',
                [
                    'superadmin',
                    'ministre',
                    'admin_espaces',
                    'admin_activites',
                    'admin_messages',
                    'admin_comptable'
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
                    WHERE statut = 'validee'
                      AND statut_paiement =
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

        // Compteurs indépendants : une erreur ci-dessus ne les empêche pas
        try {
            $badges['demandes-services.php'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM demandes_services WHERE statut = 'en_attente'"
            )->fetchColumn();
        } catch (Exception $e) {
            // table absente
        }
        try {
            if (suggestions_disponibles($pdo)) {
                $badges['suggestions.php'] = (int) $pdo->query(
                    "SELECT COUNT(*) FROM suggestions WHERE statut = 'nouvelle'"
                )->fetchColumn();
            }
        } catch (Exception $e) {
            // table absente
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
     *
     * L'année est celle du paiement ($dateCreation) : le numéro d'un reçu
     * ne change donc plus au passage à une nouvelle année. Sans date
     * fournie, l'année en cours est utilisée (comportement historique).
     */
    function ref_recu(
        int $paiementId,
        ?string $dateCreation = null
    ): string {

        $annee = $dateCreation
            ? date('y', strtotime($dateCreation))
            : date('y');

        return $annee
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
                   * Seules les réservations validées SANS AUCUN paiement
                   * expirent après 48 h. Dès qu'un paiement (acompte,
                   * paiement transféré...) existe, la réservation n'est
                   * plus « totalement impayée » : elle reste en place et
                   * son solde éventuel est suivi par le comptable.
                   */
                  AND NOT EXISTS (
                      SELECT 1 FROM paiements p WHERE p.reservation_id = r.id
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

/*
|--------------------------------------------------------------------------
| SITUATION FINANCIÈRE D'UNE RÉSERVATION — SOURCE UNIQUE
|--------------------------------------------------------------------------
| Toutes les pages (paiements, acomptes, réservations, mon-compte, bons et
| factures, statistiques, réquisitions) utilisent ces fonctions au lieu de
| recalculer chacune leurs montants.
|
|   montant initial   = tarif normal AVANT toute réduction
|                       (reservations.montant_initial, figé à la validation ;
|                        recalculé depuis le tarif pour les anciennes lignes)
| − réduction appliquée (reductions_accordees, statut « appliquee »)
| = montant net dû
|   total payé        = somme des paiements réellement encaissés
| − total remboursé   = remboursements effectués imputables à la réservation
| = payé net
|   solde             = net dû − payé net (jamais négatif)
|   trop-perçu        = payé net − net dû (jamais négatif)
*/

if (!defined('HEURE_DEBUT_SEJOUR')) {
    /**
     * Heure conventionnelle de début d'un séjour (arrivée).
     * Les séjours n'ont pas d'heure de début en base : l'échéance du solde
     * (24 h avant le début) est calculée à partir de cette heure le jour
     * d'arrivée. Exemple : arrivée le 12/10 → échéance le 11/10 à 12:00.
     */
    define('HEURE_DEBUT_SEJOUR', '12:00:00');
}

if (!defined('DELAI_SOLDE_HEURES')) {
    /** Le solde d'un acompte doit être réglé 24 h avant le début réel. */
    define('DELAI_SOLDE_HEURES', 24);
}

if (!function_exists('montant_tarif_reservation')) {
    /**
     * Tarif normal calculé à partir du tarif et de l'espace (sans réduction).
     *
     * Formule historique de admin/paiements.php et generer_bon.php :
     * tarif × nuitées × quantité (+ petit-déjeuner 5 000 / nuit / chambre
     * en séjour, + supplément VIP en créneau). 0 si le tarif n'existe plus.
     */
    function montant_tarif_reservation(
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

if (!function_exists('montant_attendu_reservation')) {
    /**
     * Montant initial (tarif normal avant réduction) d'une réservation :
     * valeur figée si elle existe, sinon calcul depuis le tarif.
     */
    function montant_attendu_reservation(
        PDO $pdo,
        int $reservationId
    ): float {

        $stmt = $pdo->prepare("SELECT montant_initial FROM reservations WHERE id = ?");
        $stmt->execute([$reservationId]);
        $fige = $stmt->fetchColumn();

        if ($fige !== false && $fige !== null) {
            return round((float) $fige, 2);
        }

        return montant_tarif_reservation($pdo, $reservationId);
    }
}

if (!function_exists('figer_montant_initial')) {
    /**
     * Fige le montant initial d'une réservation s'il ne l'est pas encore.
     * Appelée à la validation, à la saisie guichet et, pour les anciennes
     * réservations, lors de la première opération comptable.
     * Ne modifie jamais un montant déjà figé.
     */
    function figer_montant_initial(
        PDO $pdo,
        int $reservationId
    ): float {

        $montant = montant_attendu_reservation($pdo, $reservationId);

        $pdo->prepare("
            UPDATE reservations
            SET montant_initial = ?
            WHERE id = ?
              AND montant_initial IS NULL
        ")->execute([$montant, $reservationId]);

        return $montant;
    }
}

if (!function_exists('debut_reservation')) {
    /**
     * Début réel d'une réservation (timestamp) :
     * - créneau : date + heure de début ;
     * - séjour  : date d'arrivée + HEURE_DEBUT_SEJOUR.
     */
    function debut_reservation(array $r): int
    {
        $heure = !empty($r['heure_debut'])
            ? $r['heure_debut']
            : HEURE_DEBUT_SEJOUR;

        return (int) strtotime($r['date_resa'] . ' ' . $heure);
    }
}

if (!function_exists('situation_financiere_reservation')) {
    /**
     * Situation financière complète d'une réservation.
     *
     * Avec $verrouiller = true, la réservation est verrouillée
     * (SELECT ... FOR UPDATE) : à appeler dans une transaction.
     *
     * Retourne null si la réservation n'existe pas.
     */
    function situation_financiere_reservation(
        PDO $pdo,
        int $reservationId,
        bool $verrouiller = false
    ): ?array {

        $stmt = $pdo->prepare("
            SELECT id, statut, statut_paiement, date_resa, date_depart,
                   heure_debut, heure_fin, date_validation, date_limite_solde,
                   montant_initial, requisition_id
            FROM reservations
            WHERE id = ?
        " . ($verrouiller ? ' FOR UPDATE' : ''));
        $stmt->execute([$reservationId]);
        $r = $stmt->fetch();

        if (!$r) {
            return null;
        }

        $initialFige = $r['montant_initial'] !== null;
        $montantInitial = $initialFige
            ? round((float) $r['montant_initial'], 2)
            : montant_tarif_reservation($pdo, $reservationId);

        // --- Réductions ---
        $stmt = $pdo->prepare("
            SELECT ra.*, u.nom_complet AS saisi_par_nom
            FROM reductions_accordees ra
            LEFT JOIN users u ON u.id = ra.saisi_par
            WHERE ra.reservation_id = ?
            ORDER BY ra.id ASC
        ");
        $stmt->execute([$reservationId]);
        $reductions = $stmt->fetchAll();

        $reductionAppliquee = null;
        $reductionsNonAppliquees = [];
        $reductionsAnnulees = [];

        foreach ($reductions as &$red) {
            // Origine : « commerciale » (guichet) ou « requisition » (maintien du tarif)
            $red['origine'] = ($red['origine'] ?? '') === 'requisition' ? 'requisition' : 'commerciale';
        }
        unset($red);

        foreach ($reductions as $red) {
            if ($red['statut'] === 'appliquee') {
                $reductionAppliquee = $red;
            } elseif ($red['statut'] === 'non_appliquee') {
                $reductionsNonAppliquees[] = $red;
            } else {
                $reductionsAnnulees[] = $red;
            }
        }

        $montantReduction = $reductionAppliquee
            ? min($montantInitial, (float) $reductionAppliquee['montant_reduction'])
            : 0.0;

        // --- Paiements ---
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0),
                   COUNT(*),
                   MAX(CASE WHEN motif_reduction IS NOT NULL AND TRIM(motif_reduction) <> '' THEN 1 ELSE 0 END)
            FROM paiements
            WHERE reservation_id = ?
        ");
        $stmt->execute([$reservationId]);
        [$totalPaye, $nbPaiements, $reductionHistorique] = $stmt->fetch(PDO::FETCH_NUM);
        $totalPaye = round((float) $totalPaye, 2);
        $nbPaiements = (int) $nbPaiements;

        // Ancien fonctionnement (avant reductions_accordees) : un paiement
        // portant un motif_reduction soldait la réservation au montant versé.
        // Ces réservations restent considérées comme réglées, sans modifier
        // l'historique.
        $montantReductionHistorique = 0.0;
        if (!$reductionAppliquee && (int) $reductionHistorique === 1) {
            $montantReductionHistorique = round(max(0.0, $montantInitial - $totalPaye), 2);
            $montantReduction = $montantReductionHistorique;
        }

        $netDu = round(max(0.0, $montantInitial - $montantReduction), 2);

        // --- Remboursements imputables ---
        // - réquisition avec choix « remboursement » (ou autre choix sans
        //   nouvelle réservation) : l'argent est sur la réservation d'origine ;
        // - nouvelle date / autre espace : les paiements ont été transférés
        //   vers la nouvelle réservation, le remboursement (trop-perçu) lui
        //   est donc imputé, même s'il est rattaché à la réservation d'origine.
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(COALESCE(rb.montant_rembourse, rb.montant_a_rembourser)), 0)
            FROM remboursements rb
            JOIN requisitions_ministerielles rm ON rm.id = rb.requisition_id
            WHERE rb.resultat = 'effectue'
              AND (
                    (rb.reservation_id = ? AND rm.choix_client NOT IN ('nouvelle_date', 'autre_espace'))
                 OR (? IS NOT NULL AND rb.requisition_id = ? AND rm.choix_client IN ('nouvelle_date', 'autre_espace'))
              )
        ");
        $stmt->execute([
            $reservationId,
            $r['requisition_id'],
            $r['requisition_id'],
        ]);
        $totalRembourse = round((float) $stmt->fetchColumn(), 2);

        $payeNet = round($totalPaye - $totalRembourse, 2);
        $solde = round(max(0.0, $netDu - $payeNet), 2);
        $tropPercu = round(max(0.0, $payeNet - $netDu), 2);

        // --- Statut de paiement correspondant à la situation réelle ---
        if ($payeNet <= 0) {
            $statutCalcule = $r['statut_paiement'] === 'attente_paiement'
                ? 'attente_paiement'
                : 'non_paye';
        } elseif ($payeNet < $netDu) {
            $statutCalcule = 'partiellement_paye';
        } else {
            $statutCalcule = 'paye';
        }

        // --- Échéances ---
        $maintenant = time();
        $echeancePremierPaiement = null;
        $echeanceSolde = null;

        if ($r['statut'] === 'validee' && $totalPaye <= 0 && !empty($r['date_validation'])) {
            // Délai de 48 h existant (report au lundi si week-end)
            $echeancePremierPaiement = limite_paiement($r['date_validation']);
        }

        if ($r['statut'] === 'validee' && $statutCalcule === 'partiellement_paye') {
            // Solde : 24 h avant le début réel de la réservation
            $echeanceSolde = debut_reservation($r) - DELAI_SOLDE_HEURES * 3600;
        }

        $enRetard = ($echeancePremierPaiement !== null && $echeancePremierPaiement < $maintenant)
            || ($echeanceSolde !== null && $echeanceSolde < $maintenant);

        // --- État lisible ---
        if (in_array($r['statut'], ['requisitionnee', 'annulee', 'refusee', 'expiree'], true)) {
            $etat = 'clos';
        } elseif ($r['statut'] === 'en_attente') {
            $etat = 'en_attente_validation';
        } elseif ($tropPercu > 0) {
            $etat = 'trop_percu';
        } elseif ($statutCalcule === 'paye') {
            $etat = 'paye';
        } elseif ($statutCalcule === 'partiellement_paye') {
            $etat = $enRetard ? 'solde_en_retard' : 'acompte';
        } else {
            $etat = 'a_payer';
        }

        return [
            'reservation_id'            => (int) $r['id'],
            'statut_reservation'        => $r['statut'],
            'statut_paiement_stocke'    => $r['statut_paiement'],
            'statut_paiement_calcule'   => $statutCalcule,
            'montant_initial'           => $montantInitial,
            'montant_initial_fige'      => $initialFige,
            'reduction_appliquee'       => $reductionAppliquee,
            'montant_reduction'         => round($montantReduction, 2),
            'reduction_historique'      => $montantReductionHistorique,
            'prise_en_charge_requisition' => ($reductionAppliquee && $reductionAppliquee['origine'] === 'requisition'),
            'requisition_id'            => $r['requisition_id'] ? (int) $r['requisition_id'] : null,
            'reductions_non_appliquees' => $reductionsNonAppliquees,
            'reductions_annulees'       => $reductionsAnnulees,
            'reductions'                => $reductions,
            'net_du'                    => $netDu,
            'total_paye'                => $totalPaye,
            'nb_paiements'              => $nbPaiements,
            'total_rembourse'           => $totalRembourse,
            'paye_net'                  => $payeNet,
            'solde'                     => $solde,
            'trop_percu'                => $tropPercu,
            'echeance_premier_paiement' => $echeancePremierPaiement,
            'echeance_solde'            => $echeanceSolde,
            'en_retard'                 => $enRetard,
            'etat'                      => $etat,
            'encaissable'               => $r['statut'] === 'validee' && $solde > 0,
        ];
    }
}

if (!function_exists('synchroniser_statut_paiement')) {
    /**
     * Enregistre dans reservations.statut_paiement (et date_limite_solde)
     * le statut correspondant à la situation financière calculée.
     * Uniquement pour les réservations en cours (en_attente / validee) :
     * les réservations closes (réquisitionnées, expirées...) gardent leur
     * historique tel quel.
     */
    function synchroniser_statut_paiement(
        PDO $pdo,
        int $reservationId
    ): ?array {

        $s = situation_financiere_reservation($pdo, $reservationId);

        if (!$s || !in_array($s['statut_reservation'], ['en_attente', 'validee'], true)) {
            return $s;
        }

        $pdo->prepare("
            UPDATE reservations
            SET statut_paiement = ?,
                date_limite_solde = ?,
                paiement_notifie = 0
            WHERE id = ?
        ")->execute([
            $s['statut_paiement_calcule'],
            $s['echeance_solde'] ? date('Y-m-d', $s['echeance_solde']) : null,
            $reservationId,
        ]);

        return situation_financiere_reservation($pdo, $reservationId);
    }
}

if (!function_exists('libelle_etat_financier')) {
    /** Libellé et couleur d'un état financier (affichage). */
    function libelle_etat_financier(string $etat): array
    {
        return [
            'en_attente_validation' => ['En attente de validation', 'bg-slate-100 text-slate-600 border-slate-200'],
            'a_payer'               => ['À payer', 'bg-amber-50 text-amber-700 border-amber-200'],
            'acompte'               => ['Acompte versé', 'bg-sky-50 text-sky-700 border-sky-200'],
            'solde_en_retard'       => ['Solde en retard', 'bg-red-50 text-red-700 border-red-200'],
            'paye'                  => ['Payé', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'trop_percu'            => ['Trop-perçu à rembourser', 'bg-orange-50 text-orange-700 border-orange-200'],
            'clos'                  => ['Clos', 'bg-slate-100 text-slate-500 border-slate-200'],
        ][$etat] ?? [$etat, 'bg-slate-100 text-slate-600 border-slate-200'];
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

        /*
         * Une réduction appliquée sur la réservation d'origine est reportée
         * sur la nouvelle réservation (plafonnée à son montant initial) :
         * le client conserve les conditions accordées au guichet.
         */
        $resultat['reduction_reportee'] = reporter_reduction_requisition(
            $pdo,
            $origineId,
            $nouvelleReservationId,
            $requisitionId
        );

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

        /*
         * Statut, solde et trop-perçu de la nouvelle réservation : calculés
         * par la situation financière centrale (montant initial figé,
         * réduction appliquée, paiements, remboursements).
         */
        $situation = synchroniser_statut_paiement($pdo, $nouvelleReservationId);

        $total = $situation['total_paye'];
        $du = $situation['net_du'];
        $statut = $situation['statut_paiement_calcule'];
        $solde = $situation['solde'];
        $tropPercu = $situation['trop_percu'];

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
            . ' — montant net dû : ' . number_format($du, 0, ',', ' ') . ' FCFA'
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

        /*
         * Un trop-perçu n'existe que sur une base financière réelle :
         * paiements effectivement rattachés à la nouvelle réservation et
         * remboursements effectivement effectués. La valeur historique
         * operations_requisition.montant_concerne n'est jamais utilisée
         * seule pour afficher ou autoriser un remboursement.
         */

        // Trop-perçu restant : calculé par la situation financière centrale
        // (paiements − remboursements effectués − montant net dû)
        $situationValidee = $validee
            ? situation_financiere_reservation($pdo, (int)$validee['id'])
            : null;
        $tropPercu = $situationValidee ? $situationValidee['trop_percu'] : 0.0;

        $stmt = $pdo->prepare("
            SELECT COUNT(*), COALESCE(SUM(COALESCE(montant_rembourse, montant_a_rembourser)), 0)
            FROM remboursements
            WHERE requisition_id = ?
              AND resultat = 'effectue'
        ");
        $stmt->execute([$requisitionId]);
        [$nbRembourses, $montantRembourse] = $stmt->fetch(PDO::FETCH_NUM);
        $rembourse = (int)$nbRembourses > 0;
        $montantRembourse = round((float)$montantRembourse, 2);

        // Trop-perçu constaté = restant + déjà remboursé, uniquement si la
        // nouvelle réservation a réellement reçu des paiements.
        $aDesPaiements = $situationValidee && $situationValidee['total_paye'] > 0;
        $tropPercuConstate = $aDesPaiements ? round($tropPercu + $montantRembourse, 2) : 0.0;

        return [
            'liste'      => $liste,
            'validee'    => $validee,
            'trop_percu' => $tropPercu,
            'trop_percu_constate' => $tropPercuConstate,
            'montant_rembourse' => $montantRembourse,
            'situation'  => $situationValidee,
            'rembourse'  => $rembourse,
            'a_rembourser' => $validee !== null && $aDesPaiements && $tropPercu > 0 && !$rembourse,
        ];
    }
}

if (!function_exists('colonne_existe')) {
    /** Vrai si la colonne existe (cache par requête) — code compatible avant/après migration. */
    function colonne_existe(PDO $pdo, string $table, string $colonne): bool
    {
        static $cache = [];
        $cle = $table . '.' . $colonne;
        if (!array_key_exists($cle, $cache)) {
            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $st->execute([$table, $colonne]);
                $cache[$cle] = (int)$st->fetchColumn() > 0;
            } catch (PDOException $e) {
                $cache[$cle] = false;
            }
        }
        return $cache[$cle];
    }
}

if (!function_exists('partenaires_disponibles')) {
    /** Module partenaires installé (migration exécutée). */
    function partenaires_disponibles(PDO $pdo): bool
    {
        return colonne_existe($pdo, 'partenaires', 'id')
            && colonne_existe($pdo, 'users', 'partenaire_id')
            && colonne_existe($pdo, 'reservations', 'partenaire_id');
    }
}

if (!function_exists('role_partenaire_disponible')) {
    /** Le rôle « partenaire » existe dans users.role (migration exécutée). */
    function role_partenaire_disponible(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
                $ok = $col && str_contains((string)$col['Type'], "'partenaire'");
            } catch (PDOException $e) {
                $ok = false;
            }
        }
        return $ok;
    }
}

if (!function_exists('is_partenaire')) {
    /** Utilisateur connecté avec le rôle « partenaire ». */
    function is_partenaire(): bool
    {
        return ($_SESSION['role'] ?? '') === 'partenaire';
    }
}

if (!function_exists('partenaire_utilisateur')) {
    /**
     * Partenaire ACTIF associé à un compte client (null = client classique).
     * Un partenaire désactivé ne donne plus le statut partenaire.
     */
    function partenaire_utilisateur(PDO $pdo, int $userId): ?array
    {
        if (!$userId || !partenaires_disponibles($pdo) || !role_partenaire_disponible($pdo)) {
            return null;
        }
        // Compte partenaire = rôle « partenaire » + fiche partenaire active.
        // Un compte « user » portant un partenaire_id n'est PAS un partenaire.
        $st = $pdo->prepare("
            SELECT p.*
            FROM users u
            JOIN partenaires p ON p.id = u.partenaire_id
            WHERE u.id = ? AND p.actif = 1 AND u.role = 'partenaire'
        ");
        $st->execute([$userId]);
        return $st->fetch() ?: null;
    }
}

if (!function_exists('attribuer_partenaire_reservation')) {
    /**
     * Rattache une réservation au partenaire actif du client (s'il en a un).
     * Appelée juste après la création d'une réservation : le circuit de
     * réservation reste le même pour tous, seule l'attribution change.
     */
    function attribuer_partenaire_reservation(PDO $pdo, int $reservationId, int $userId): void
    {
        $partenaire = partenaire_utilisateur($pdo, $userId);
        if ($partenaire) {
            $pdo->prepare("UPDATE reservations SET partenaire_id = ? WHERE id = ?")
                ->execute([(int)$partenaire['id'], $reservationId]);
        }
    }
}

if (!function_exists('suggestions_disponibles')) {
    /** Boîte à suggestions installée (migration exécutée). */
    function suggestions_disponibles(PDO $pdo): bool
    {
        return colonne_existe($pdo, 'suggestions', 'contenu');
    }
}

if (!function_exists('services_cycle_disponible')) {
    /** Cycle complet des demandes de services disponible (migration exécutée). */
    function services_cycle_disponible(PDO $pdo): bool
    {
        return colonne_existe($pdo, 'demandes_services', 'date_prise_en_charge');
    }
}

if (!function_exists('libelle_statut_service')) {
    /** Libellé et couleur d'un statut de demande de service (« traitee » = ancien « réalisée »). */
    function libelle_statut_service(string $statut): array
    {
        return [
            'en_attente' => ['En attente', 'bg-amber-100 text-amber-700'],
            'en_cours'   => ['En cours', 'bg-sky-100 text-sky-700'],
            'realisee'   => ['Réalisée', 'bg-emerald-100 text-emerald-700'],
            'traitee'    => ['Réalisée', 'bg-emerald-100 text-emerald-700'],
            'refusee'    => ['Refusée', 'bg-red-100 text-red-700'],
            'annulee'    => ['Annulée', 'bg-slate-100 text-slate-600'],
        ][$statut] ?? [$statut, 'bg-slate-100 text-slate-600'];
    }
}

if (!function_exists('ref_resa')) {
    /** Référence de dossier d'une réservation : RESA-152. */
    function ref_resa(?int $reservationId): string
    {
        return $reservationId ? 'RESA-' . $reservationId : '';
    }
}

if (!function_exists('ref_req')) {
    /** Référence d'une réquisition : REQ-27. */
    function ref_req(?int $requisitionId): string
    {
        return $requisitionId ? 'REQ-' . $requisitionId : '';
    }
}

if (!function_exists('observations_types_disponibles')) {
    /**
     * Types d'objets pouvant recevoir une observation, d'après la
     * colonne observations.cible_type (les types « requisition » et
     * « remboursement » n'existent qu'après la migration).
     */
    function observations_types_disponibles(PDO $pdo): array
    {
        static $types = null;
        if ($types === null) {
            $types = ['reservation', 'paiement', 'activite', 'espace'];
            try {
                $col = $pdo->query("SHOW COLUMNS FROM observations LIKE 'cible_type'")->fetch();
                if ($col && preg_match_all("/'([^']+)'/", (string)$col['Type'], $m)) {
                    $types = $m[1];
                }
            } catch (PDOException $e) {
                // Table absente : types par défaut
            }
        }
        return $types;
    }
}

if (!function_exists('observations_regles')) {
    /**
     * Règles d'accès aux observations, par type d'objet :
     *  - voir    : rôles qui voient (et peuvent créer) les observations ;
     *  - repondre: rôles responsables qui peuvent répondre.
     * Le superadmin voit tout ; l'auteur d'une observation peut toujours
     * répondre dans son propre fil. Ministre : lecture et observation,
     * sans autre droit de modification.
     */
    function observations_regles(): array
    {
        return [
            'voir' => [
                'espace'        => ['admin_espaces', 'ministre'],
                'reservation'   => ['admin_espaces', 'admin_comptable', 'ministre'],
                'paiement'      => ['admin_comptable', 'ministre'],
                'activite'      => ['admin_activites', 'ministre'],
                'requisition'   => ['admin_comptable', 'ministre'],
                'remboursement' => ['admin_comptable', 'ministre'],
            ],
            'repondre' => [
                'espace'        => ['admin_espaces'],
                'reservation'   => ['admin_espaces', 'admin_comptable'],
                'paiement'      => ['admin_comptable'],
                'activite'      => ['admin_activites'],
                'requisition'   => ['admin_comptable'],
                'remboursement' => ['admin_comptable'],
            ],
        ];
    }
}

if (!function_exists('nb_observations')) {
    /** Nombre de fils d'observation ouverts sur un objet (0 si type indisponible). */
    function nb_observations(PDO $pdo, string $type, int $id): int
    {
        if (!in_array($type, observations_types_disponibles($pdo), true)) {
            return 0;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM observations WHERE cible_type = ? AND cible_id = ? AND parent_id IS NULL");
        $stmt->execute([$type, $id]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('references_dossiers')) {
    /**
     * Chaîne de références RESA-A → REQ-X → RESA-B pour une liste de
     * réservations (une seule requête, utilisable dans les listes).
     *
     * Pour chaque réservation :
     *  - requisition_id / origine_id : si elle est issue d'une réquisition
     *    (réservation B), la réquisition et la réservation initiale A ;
     *  - requisition_id / nouvelle_id : si elle a elle-même été
     *    réquisitionnée (réservation A), la réquisition et la nouvelle
     *    réservation B active (validée, sinon en attente) ;
     *  - resa, req, origine, nouvelle : les mêmes au format RESA-/REQ-.
     *
     * Référence de dossier et référence de réquisition ne sont jamais
     * confondues avec la référence de transaction (paiements.reference).
     */
    function references_dossiers(PDO $pdo, array $reservationIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $reservationIds))));
        $refs = [];

        foreach ($ids as $rid) {
            $refs[$rid] = [
                'reservation_id' => $rid,
                'resa'           => ref_resa($rid),
                'requisition_id' => null,
                'req'            => '',
                'origine_id'     => null,
                'origine'        => '',
                'nouvelle_id'    => null,
                'nouvelle'       => '',
            ];
        }

        if (!$ids) {
            return $refs;
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        // Réservations B : issues d'une réquisition
        $stmt = $pdo->prepare("
            SELECT r.id, r.requisition_id, rm.reservation_id AS origine_id
            FROM reservations r
            JOIN requisitions_ministerielles rm ON rm.id = r.requisition_id
            WHERE r.id IN ($in)
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $rid = (int)$row['id'];
            $refs[$rid]['requisition_id'] = (int)$row['requisition_id'];
            $refs[$rid]['req'] = ref_req((int)$row['requisition_id']);
            $refs[$rid]['origine_id'] = (int)$row['origine_id'];
            $refs[$rid]['origine'] = ref_resa((int)$row['origine_id']);
        }

        // Réservations A : réquisitionnées (nouvelle réservation active éventuelle)
        $stmt = $pdo->prepare("
            SELECT rm.reservation_id, rm.id AS requisition_id,
                   (
                       SELECT n.id
                       FROM reservations n
                       WHERE n.requisition_id = rm.id
                         AND n.statut IN ('validee', 'en_attente')
                       ORDER BY (n.statut = 'validee') DESC, n.id DESC
                       LIMIT 1
                   ) AS nouvelle_id
            FROM requisitions_ministerielles rm
            WHERE rm.reservation_id IN ($in)
            ORDER BY rm.id ASC
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $rid = (int)$row['reservation_id'];
            if ($refs[$rid]['requisition_id'] === null) {
                $refs[$rid]['requisition_id'] = (int)$row['requisition_id'];
                $refs[$rid]['req'] = ref_req((int)$row['requisition_id']);
            }
            if ($row['nouvelle_id']) {
                $refs[$rid]['nouvelle_id'] = (int)$row['nouvelle_id'];
                $refs[$rid]['nouvelle'] = ref_resa((int)$row['nouvelle_id']);
            }
        }

        return $refs;
    }
}

if (!function_exists('references_dossier')) {
    /** Chaîne de références d'une seule réservation (voir references_dossiers). */
    function references_dossier(PDO $pdo, int $reservationId): array
    {
        return references_dossiers($pdo, [$reservationId])[$reservationId] ?? [
            'reservation_id' => $reservationId, 'resa' => ref_resa($reservationId),
            'requisition_id' => null, 'req' => '', 'origine_id' => null, 'origine' => '',
            'nouvelle_id' => null, 'nouvelle' => '',
        ];
    }
}

if (!function_exists('libelle_references_dossier')) {
    /**
     * Libellé court et lisible : « RESA-152 · REQ-27 · issue de RESA-98 »
     * (ou « RESA-98 · REQ-27 · remplacée par RESA-152 »).
     */
    function libelle_references_dossier(array $refs): string
    {
        $parts = [$refs['resa']];
        if ($refs['req'] !== '') {
            $parts[] = $refs['req'];
        }
        if ($refs['origine'] !== '') {
            $parts[] = 'issue de ' . $refs['origine'];
        } elseif ($refs['nouvelle'] !== '') {
            $parts[] = 'remplacée par ' . $refs['nouvelle'];
        }
        return implode(' · ', $parts);
    }
}

if (!function_exists('historique_financier_reservation')) {
    /**
     * Historique financier chronologique d'un dossier (lecture seule),
     * reconstruit à partir des tables existantes, sans rien modifier :
     * paiements, réductions (saisie et changements de statut),
     * remboursements, opérations de réquisition et observations.
     *
     * Pour une réservation B issue d'une réquisition, les éléments de la
     * réservation initiale A et de la réquisition sont inclus.
     *
     * Chaque entrée : date, action, auteur, objet (références), montant,
     * resultat, transaction (référence réelle), detail.
     */
    function historique_financier_reservation(PDO $pdo, int $reservationId): array
    {
        $refs = references_dossier($pdo, $reservationId);
        $resaIds = array_values(array_filter([$reservationId, $refs['origine_id']]));
        $in = implode(',', array_fill(0, count($resaIds), '?'));
        $entrees = [];

        $ajouter = function (?string $date, string $action, ?string $auteur, string $objet, $montant, string $resultat, string $transaction = '', string $detail = '') use (&$entrees) {
            if (!$date) {
                return;
            }
            $entrees[] = [
                'date'        => $date,
                'action'      => $action,
                'auteur'      => $auteur ?: '—',
                'objet'       => $objet,
                'montant'     => $montant === null ? null : round((float)$montant, 2),
                'resultat'    => $resultat,
                'transaction' => $transaction,
                'detail'      => $detail,
            ];
        };

        // Paiements (un paiement transféré apparaît sur la réservation qui le porte aujourd'hui)
        $stmt = $pdo->prepare("
            SELECT p.id, p.reservation_id, p.montant, p.mode, p.reference, p.note, p.created_at, u.nom_complet
            FROM paiements p
            LEFT JOIN users u ON u.id = p.enregistre_par
            WHERE p.reservation_id IN ($in)
        ");
        $stmt->execute($resaIds);
        $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money', 'moov_money' => 'Moov Money', 'virement' => 'Virement', 'cheque' => 'Chèque'];
        foreach ($stmt->fetchAll() as $p) {
            $transfere = str_contains((string)$p['note'], 'Paiement transféré');
            $ajouter(
                $p['created_at'],
                'Paiement encaissé',
                $p['nom_complet'],
                ref_recu((int)$p['id'], $p['created_at']) . ' · ' . ref_resa((int)$p['reservation_id']),
                $p['montant'],
                ($modes[$p['mode']] ?? $p['mode']) . ($transfere ? ' — transféré depuis la réservation initiale' : ''),
                (string)($p['reference'] ?? '')
            );
        }

        // Réductions et maintien du tarif
        $stmt = $pdo->prepare("
            SELECT ra.*, us.nom_complet AS saisi_nom, um.nom_complet AS modifie_nom
            FROM reductions_accordees ra
            LEFT JOIN users us ON us.id = ra.saisi_par
            LEFT JOIN users um ON um.id = ra.statut_modifie_par
            WHERE ra.reservation_id IN ($in)
        ");
        $stmt->execute($resaIds);
        foreach ($stmt->fetchAll() as $ra) {
            $estReq = ($ra['origine'] ?? '') === 'requisition';
            $libelle = $estReq ? 'Maintien du tarif (réquisition)' : 'Réduction commerciale';
            $ajouter(
                $ra['created_at'],
                $libelle . ' accordée',
                $ra['saisi_nom'],
                ref_resa((int)$ra['reservation_id']),
                $ra['montant_reduction'],
                ['appliquee' => 'Appliquée', 'non_appliquee' => 'Non utilisée', 'annulee' => 'Annulée'][$ra['statut']] ?? $ra['statut'],
                '',
                trim((string)$ra['motif'])
                    . (!empty($ra['autorise_par']) ? ' — autorisée par ' . $ra['autorise_par'] : '')
                    . (!empty($ra['reference_accord']) ? ' (accord ' . $ra['reference_accord'] . ')' : '')
            );
            if (!empty($ra['statut_modifie_le']) && $ra['statut'] !== 'appliquee') {
                $ajouter(
                    $ra['statut_modifie_le'],
                    $libelle . ' ' . ($ra['statut'] === 'annulee' ? 'annulée' : 'marquée non utilisée'),
                    $ra['modifie_nom'],
                    ref_resa((int)$ra['reservation_id']),
                    $ra['montant_reduction'],
                    $ra['statut'] === 'annulee' ? 'Annulée' : 'Non utilisée',
                    '',
                    trim((string)($ra['motif_statut'] ?? ''))
                );
            }
        }

        // Réquisition, opérations et remboursements
        if ($refs['requisition_id']) {
            $stmt = $pdo->prepare("
                SELECT rm.*, u.nom_complet AS declenche_nom, t.nom_complet AS traite_nom
                FROM requisitions_ministerielles rm
                LEFT JOIN users u ON u.id = rm.declenche_par
                LEFT JOIN users t ON t.id = rm.traite_par
                WHERE rm.id = ?
            ");
            $stmt->execute([$refs['requisition_id']]);
            if ($rm = $stmt->fetch()) {
                $objetReq = ref_req((int)$rm['id']) . ' · ' . ref_resa((int)$rm['reservation_id']);
                $ajouter($rm['date_declenchee'], 'Réquisition déclenchée', $rm['declenche_nom'], $objetReq, null, 'Réservation initiale réquisitionnée');
                $ajouter($rm['date_choix'], 'Choix du client', null, $objetReq, null,
                    ['annulation' => 'Annulation', 'remboursement' => 'Remboursement', 'nouvelle_date' => 'Nouvelle date', 'autre_espace' => 'Autre espace'][$rm['choix_client']] ?? (string)$rm['choix_client']);
                if ($rm['statut'] === 'cloturee') {
                    $ajouter($rm['date_traitement'], 'Réquisition clôturée', $rm['traite_nom'], $objetReq, null, 'Clôturée', '', trim((string)($rm['note_traitement'] ?? '')));
                }
            }

            $stmt = $pdo->prepare("
                SELECT rb.*, u.nom_complet AS traite_nom
                FROM remboursements rb
                LEFT JOIN users u ON u.id = rb.traite_par
                WHERE rb.requisition_id = ?
            ");
            $stmt->execute([$refs['requisition_id']]);
            foreach ($stmt->fetchAll() as $rb) {
                $effectue = ($rb['resultat'] ?? '') === 'effectue';
                $ajouter(
                    $rb['date_traitement'] ?: $rb['created_at'],
                    'Remboursement ' . ($effectue ? 'effectué' : 'enregistré'),
                    $rb['traite_nom'],
                    'BR-' . date('Y', strtotime($rb['date_traitement'] ?? $rb['created_at'])) . '-' . str_pad((string)$rb['id'], 6, '0', STR_PAD_LEFT)
                        . ' · ' . ref_req((int)$rb['requisition_id']),
                    $effectue ? ($rb['montant_rembourse'] ?? $rb['montant_a_rembourser']) : $rb['montant_a_rembourser'],
                    $effectue ? 'Effectué' : 'Non effectué',
                    (string)($rb['reference'] ?? ''),
                    trim((string)$rb['motif'])
                );
            }
        }

        // Observations associées (fils principaux)
        $cibles = [['reservation', $reservationId]];
        if ($refs['origine_id']) {
            $cibles[] = ['reservation', $refs['origine_id']];
        }
        if ($refs['requisition_id']) {
            $cibles[] = ['requisition', $refs['requisition_id']];
        }
        foreach ($cibles as [$type, $cid]) {
            try {
                $stmt = $pdo->prepare("
                    SELECT o.id, o.contenu, o.created_at, u.nom_complet,
                           (SELECT COUNT(*) FROM observations r WHERE r.parent_id = o.id) AS nb_reponses
                    FROM observations o
                    LEFT JOIN users u ON u.id = o.auteur_id
                    WHERE o.cible_type = ? AND o.cible_id = ? AND o.parent_id IS NULL
                ");
                $stmt->execute([$type, $cid]);
                foreach ($stmt->fetchAll() as $o) {
                    $ajouter(
                        $o['created_at'],
                        'Observation',
                        $o['nom_complet'],
                        ($type === 'requisition' ? ref_req((int)$cid) : ref_resa((int)$cid)),
                        null,
                        (int)$o['nb_reponses'] > 0 ? $o['nb_reponses'] . ' réponse(s)' : 'Sans réponse',
                        '',
                        mb_strimwidth((string)$o['contenu'], 0, 160, '…')
                    );
                }
            } catch (PDOException $e) {
                // Type d'observation non encore disponible (migration non exécutée)
            }
        }

        usort($entrees, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $entrees;
    }
}

if (!function_exists('requisition_remboursement_attendu')) {
    /**
     * Remboursement réellement dû au client pour une réquisition, calculé
     * uniquement sur une base financière réelle (paiements encaissés −
     * remboursements effectués). Règle unique pour requisition-detail.php
     * et requisitions.php :
     *  - choix « remboursement » : tout ce que le client a réellement payé
     *    sur la réservation d'origine (payé net) ;
     *  - « nouvelle date » / « autre espace » : le trop-perçu réel de la
     *    nouvelle réservation validée ;
     *  - sinon (ou si rien n'a été payé) : aucun remboursement.
     *
     * @return array{type: ?string, montant: float, reservation_id: ?int}
     */
    function requisition_remboursement_attendu(PDO $pdo, int $requisitionId): array
    {
        $aucun = ['type' => null, 'montant' => 0.0, 'reservation_id' => null];

        $stmt = $pdo->prepare("SELECT reservation_id, choix_client, statut FROM requisitions_ministerielles WHERE id = ?");
        $stmt->execute([$requisitionId]);
        $rq = $stmt->fetch();

        if (!$rq || $rq['statut'] === 'cloturee') {
            return $aucun;
        }

        if ($rq['choix_client'] === 'remboursement') {
            $s = situation_financiere_reservation($pdo, (int)$rq['reservation_id']);
            $montant = $s ? round((float)$s['paye_net'], 2) : 0.0;
            return $montant > 0
                ? ['type' => 'remboursement', 'montant' => $montant, 'reservation_id' => (int)$rq['reservation_id']]
                : $aucun;
        }

        if (in_array($rq['choix_client'], ['nouvelle_date', 'autre_espace'], true)) {
            $suivi = requisition_suivi_nouvelle_reservation($pdo, $requisitionId);
            return $suivi['a_rembourser']
                ? ['type' => 'trop_percu', 'montant' => round((float)$suivi['trop_percu'], 2), 'reservation_id' => (int)$suivi['validee']['id']]
                : $aucun;
        }

        return $aucun;
    }
}

if (!function_exists('reservation_requisition_blocage')) {
    /**
     * Raison empêchant de refuser ou de remettre en attente une nouvelle
     * réservation B issue d'une réquisition (null = action possible).
     * Ne concerne que les réservations B (requisition_id renseigné) :
     *  - réquisition déjà clôturée : B n'est plus modifiable ;
     *  - B porte des paiements (transférés depuis A ou encaissés) ;
     *  - remise en attente d'une B refusée, annulée ou expirée : le client
     *    dépose une nouvelle demande (une seule B active à la fois).
     */
    function reservation_requisition_blocage(PDO $pdo, int $reservationId, string $action): ?string
    {
        $stmt = $pdo->prepare("
            SELECT r.statut, r.requisition_id, rm.statut AS requisition_statut,
                   (SELECT COUNT(*) FROM paiements p WHERE p.reservation_id = r.id) AS nb_paiements
            FROM reservations r
            LEFT JOIN requisitions_ministerielles rm ON rm.id = r.requisition_id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
        $r = $stmt->fetch();

        if (!$r || empty($r['requisition_id'])) {
            return null;
        }

        $req = 'la réquisition #' . (int)$r['requisition_id'];

        if ($r['requisition_statut'] === 'cloturee') {
            return "Cette réservation est issue de $req, déjà clôturée : elle ne peut plus être modifiée.";
        }
        if ((int)$r['nb_paiements'] > 0) {
            return "Cette réservation (issue de $req) porte déjà des paiements : elle ne peut être ni refusée ni remise en attente. Contactez le service comptable.";
        }
        if ($action === 'annuler' && in_array($r['statut'], ['refusee', 'annulee', 'expiree'], true)) {
            return "Cette réservation issue de $req ne peut pas être remise en attente : le client dépose une nouvelle demande depuis son espace.";
        }

        return null;
    }
}

if (!function_exists('requisition_blocages_cloture')) {
    /**
     * Raisons empêchant de clôturer une réquisition « nouvelle date » /
     * « autre espace » (liste vide = clôture possible).
     *
     * Une réquisition clôturée doit correspondre à un dossier réellement
     * terminé :
     *  - nouvelle réservation validée ;
     *  - solde de la nouvelle réservation à 0 (avec le maintien du tarif,
     *    il ne reste que ce que le client devait déjà) ;
     *  - aucun trop-perçu restant à rembourser ;
     *  - aucun remboursement enregistré mais non effectué.
     */
    function requisition_blocages_cloture(PDO $pdo, int $requisitionId): array
    {
        $suivi = requisition_suivi_nouvelle_reservation($pdo, $requisitionId);
        $fmt = fn($m) => number_format((float) $m, 0, ',', ' ');
        $blocages = [];

        if ($suivi['validee'] === null) {
            $blocages[] = 'La nouvelle réservation du client n’existe pas encore ou n’a pas été validée.';
        } else {
            $s = $suivi['situation'];
            if ($s && $s['solde'] > 0) {
                $blocages[] = 'Il reste ' . $fmt($s['solde']) . ' FCFA à encaisser sur la nouvelle réservation #'
                    . (int) $suivi['validee']['id'] . '.';
            }
            if ($s && $s['trop_percu'] > 0) {
                $blocages[] = 'Un trop-perçu de ' . $fmt($s['trop_percu']) . ' FCFA doit d’abord être remboursé.';
            }
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM remboursements
            WHERE requisition_id = ?
              AND (resultat IS NULL OR resultat <> 'effectue')
        ");
        $stmt->execute([$requisitionId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $blocages[] = 'Un remboursement est enregistré mais pas encore effectué.';
        }

        return $blocages;
    }
}

if (!function_exists('reductions_origine_disponible')) {
    /**
     * La colonne reductions_accordees.origine existe-t-elle ?
     * (migration « origine des réductions »). Sans elle, toutes les
     * réductions sont considérées comme commerciales.
     */
    function reductions_origine_disponible(PDO $pdo): bool
    {
        static $disponible = null;
        if ($disponible === null) {
            $disponible = (bool) $pdo->query("SHOW COLUMNS FROM reductions_accordees LIKE 'origine'")->fetch();
        }
        return $disponible;
    }
}

if (!function_exists('estimation_maintien_tarif')) {
    /**
     * Garantie de l'ancien tarif pour une réservation issue d'une réquisition.
     *
     * Le client ne doit jamais devoir plus pour la nouvelle réservation (B)
     * que ce qu'il devait réellement pour la réservation réquisitionnée (A) :
     * montant initial de A − réduction appliquée sur A.
     *
     * Retourne null si la réservation n'est pas issue d'une réquisition, sinon :
     *  - origine_id, requisition_id ;
     *  - net_du_origine        : montant réellement dû pour A ;
     *  - reduction_origine     : réduction appliquée sur A (ligne) ou null ;
     *  - montant_initial       : tarif normal de B (figé ou calculé) ;
     *  - report_commercial     : réduction de A qui serait reportée (plafonnée) ;
     *  - prise_en_charge       : montant pris en charge suite à la réquisition
     *                            (0 si B ne coûte pas plus que A).
     */
    function estimation_maintien_tarif(PDO $pdo, int $nouvelleReservationId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT r.requisition_id, rm.reservation_id AS origine_id
            FROM reservations r
            JOIN requisitions_ministerielles rm ON rm.id = r.requisition_id
            WHERE r.id = ?
        ");
        $stmt->execute([$nouvelleReservationId]);
        $lien = $stmt->fetch();

        if (!$lien || empty($lien['origine_id']) || (int) $lien['origine_id'] === $nouvelleReservationId) {
            return null;
        }

        $origineId = (int) $lien['origine_id'];
        $sOrigine = situation_financiere_reservation($pdo, $origineId);
        if (!$sOrigine) {
            return null;
        }

        $montantInitial = montant_attendu_reservation($pdo, $nouvelleReservationId);
        $reductionOrigine = $sOrigine['reduction_appliquee'];
        $reportCommercial = $reductionOrigine
            ? min((float) $reductionOrigine['montant_reduction'], $montantInitial)
            : 0.0;

        $netDuOrigine = $sOrigine['net_du'];
        $priseEnCharge = ($montantInitial - $reportCommercial) > $netDuOrigine + 0.001
            ? round($montantInitial - $netDuOrigine, 2)
            : 0.0;

        return [
            'origine_id'        => $origineId,
            'requisition_id'    => (int) $lien['requisition_id'],
            'net_du_origine'    => $netDuOrigine,
            'reduction_origine' => $reductionOrigine,
            'montant_initial'   => $montantInitial,
            'report_commercial' => $reportCommercial,
            'prise_en_charge'   => $priseEnCharge,
        ];
    }
}

if (!function_exists('reporter_reduction_requisition')) {
    /**
     * Réductions de la nouvelle réservation (B) issue d'une réquisition.
     *
     * 1. B coûte plus que ce qui était réellement dû pour A :
     *    une seule réduction « appliquée » d'origine « requisition » est créée
     *    sur B, égale à (montant initial de B − montant dû pour A).
     *    Le client doit ainsi exactement ce qu'il devait pour A.
     *    La réduction commerciale éventuelle de A n'est ni reportée ni modifiée :
     *    elle reste sur A (historique) et la prise en charge y est liée
     *    (reportee_de) pour la traçabilité.
     *
     * 2. Sinon (B coûte autant ou moins) : comportement existant — la
     *    réduction appliquée de A est reportée sur B, plafonnée au montant
     *    initial de B ; le trop-perçu éventuel suit le circuit habituel.
     *
     * Rien n'est fait si B a déjà une réduction appliquée.
     * Retourne le montant de la réduction créée sur B.
     *
     * DOIT être appelée à l'intérieur d'une transaction.
     */
    function reporter_reduction_requisition(
        PDO $pdo,
        int $origineId,
        int $nouvelleReservationId,
        int $requisitionId
    ): float {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM reductions_accordees
            WHERE reservation_id = ?
              AND statut = 'appliquee'
        ");
        $stmt->execute([$nouvelleReservationId]);

        if ((int) $stmt->fetchColumn() > 0) {
            return 0.0;
        }

        $estimation = estimation_maintien_tarif($pdo, $nouvelleReservationId);
        $origine = $estimation['reduction_origine'] ?? null;
        $fmt = fn($m) => number_format((float) $m, 0, ',', ' ');

        $saisiPar = (int) ($_SESSION['user_id'] ?? 0);
        if ($saisiPar <= 0) {
            $stmt = $pdo->prepare("SELECT declenche_par FROM requisitions_ministerielles WHERE id = ?");
            $stmt->execute([$requisitionId]);
            $saisiPar = (int) $stmt->fetchColumn() ?: (int) ($origine['saisi_par'] ?? 0);
        }

        // --- 1. Maintien de l'ancien tarif : prise en charge suite à réquisition ---
        if ($estimation && $estimation['prise_en_charge'] > 0) {

            $montant = $estimation['prise_en_charge'];
            $motif = 'Maintien du tarif — réquisition #' . $requisitionId
                . ' : réservation #' . $origineId . ' due ' . $fmt($estimation['net_du_origine']) . ' FCFA'
                . ($origine ? ' (dont ' . $fmt($origine['montant_reduction']) . ' FCFA de réduction commerciale sur #' . $origineId . ')' : '')
                . ', nouvelle réservation au tarif normal de ' . $fmt($estimation['montant_initial']) . ' FCFA.';

            $colonnes = 'reservation_id, montant_reduction, pourcentage, motif, autorise_par, reference_accord, statut, reportee_de, saisi_par';
            $valeurs  = '?, ?, NULL, ?, ?, ?, \'appliquee\', ?, ?';
            if (reductions_origine_disponible($pdo)) {
                $colonnes .= ', origine';
                $valeurs  .= ', \'requisition\'';
            }

            $pdo->prepare("INSERT INTO reductions_accordees ($colonnes) VALUES ($valeurs)")->execute([
                $nouvelleReservationId,
                $montant,
                $motif,
                'Réquisition #' . $requisitionId,
                'REQ-' . $requisitionId,
                $origine ? (int) $origine['id'] : null,
                $saisiPar,
            ]);

            log_activity(
                'maintien_tarif_requisition',
                'reservations',
                'Maintien du tarif (réquisition #' . $requisitionId . ') : ' . $fmt($montant)
                    . ' FCFA pris en charge sur la réservation #' . $nouvelleReservationId
                    . ' — le client doit ' . $fmt($estimation['net_du_origine']) . ' FCFA comme pour la réservation #' . $origineId
            );

            return $montant;
        }

        // --- 2. Comportement existant : report de la réduction de A ---
        if (!$origine) {
            return 0.0;
        }

        $montant = min(
            (float) $origine['montant_reduction'],
            montant_attendu_reservation($pdo, $nouvelleReservationId)
        );

        if ($montant <= 0) {
            return 0.0;
        }

        $pdo->prepare("
            INSERT INTO reductions_accordees
                (reservation_id, montant_reduction, pourcentage, motif, autorise_par,
                 reference_accord, statut, reportee_de, saisi_par)
            VALUES (?, ?, NULL, ?, ?, ?, 'appliquee', ?, ?)
        ")->execute([
            $nouvelleReservationId,
            $montant,
            'Report de la réduction de la réservation #' . $origineId
                . ' (réquisition #' . $requisitionId . ')'
                . (!empty($origine['motif']) ? ' — ' . $origine['motif'] : ''),
            $origine['autorise_par'],
            $origine['reference_accord'],
            (int) $origine['id'],
            $saisiPar ?: (int) $origine['saisi_par'],
        ]);

        log_activity(
            'reduction_reportee',
            'reservations',
            'Réduction de ' . $fmt($montant) . ' FCFA reportée de la réservation #'
                . $origineId . ' vers la réservation #' . $nouvelleReservationId
                . ' (réquisition #' . $requisitionId . ')'
        );

        return $montant;
    }
}
