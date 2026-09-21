<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array {
        if (!is_logged_in()) return null;
        return [
            'id'          => (int)$_SESSION['user_id'],
            'nom_complet' => $_SESSION['nom_complet'] ?? '',
            'email'       => $_SESSION['email']       ?? '',
            'role'        => $_SESSION['role']        ?? 'user',
        ];
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return in_array($_SESSION['role'] ?? '', [
            'superadmin','ministre','admin_espaces',
            'admin_activites','admin_messages','admin_comptable'
        ], true);
    }
}

if (!function_exists('is_superadmin')) {
    function is_superadmin(): bool {
        return ($_SESSION['role'] ?? '') === 'superadmin';
    }
}

if (!function_exists('require_role')) {
    function require_role(string|array $roles, string $redirect = '../index.php'): void {
        if (!is_logged_in()) { header('Location: ../login.php'); exit; }
        $role    = $_SESSION['role'] ?? 'user';
        $allowed = is_array($roles) ? $roles : [$roles];
        $allowed[] = 'superadmin';
        if (!in_array($role, $allowed, true)) { header('Location: ' . $redirect); exit; }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(string $redirect = '../index.php'): void {
        require_role(['admin_espaces','admin_activites','admin_messages','admin_comptable','ministre'], $redirect);
    }
}

if (!function_exists('is_readonly_admin')) {
    /**
     * true si le rôle connecté n'a qu'un accès de supervision (lecture +
     * observations) sur les modules métier : admin_dg et ministre.
     * Utilisé pour masquer les boutons d'action et bloquer les POST
     * d'ajout/modification/suppression sur les pages de gestion.
     */
    function is_readonly_admin(): bool {
        return in_array($_SESSION['role'] ?? '', ['ministre'], true);
    }
}

if (!function_exists('require_client')) {
    /**
     * Bloque l'accès aux pages réservées aux clients (réservation, "mon
     * compte") pour tout compte de service (superadmin, admin_dg, ministre,
     * admin_espaces, etc.). Ce sont des comptes de service, pas des comptes
     * clients — ils ne doivent jamais réserver ni avoir un espace client.
     */
    function require_client(string $redirect = 'admin/dashboard.php'): void {
        if (!is_logged_in()) { header('Location: login.php'); exit; }
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
     *  - Mode créneau (dateFin = null) : SEULE une réservation déjà VALIDÉE
     *    bloque le créneau, jamais une simple demande "en attente" — donc
     *    plusieurs personnes peuvent demander le même créneau tant que
     *    l'admin espace n'a validé aucune d'entre elles. Une fois validée,
     *    une marge de 2h après la fin du créneau est également bloquée.
     *
     *  - Mode séjour (dateFin renseignée) : logique d'inventaire inchangée
     *    (chambres) — les demandes en attente comptent aussi, car il s'agit
     *    d'un stock physique réel (nombre de chambres), pas d'un simple
     *    créneau horaire.
     */
    function tarif_disponible(PDO $pdo, int $tarifId, string $dateDebut, ?string $dateFin = null,
                               ?string $heureDebut = null, ?string $heureFin = null,
                               ?int $excludeId = null, int $quantiteDemandee = 1): bool {
        $t = $pdo->prepare("SELECT quantite_disponible FROM tarifs WHERE id = ?");
        $t->execute([$tarifId]);
        $qte    = $t->fetchColumn();
        $limite = ($qte !== false && $qte !== null) ? (int)$qte : 1;
        if ($limite <= 1) $quantiteDemandee = min($quantiteDemandee, 1);

        if ($dateFin) {
            // Séjour : chevauchement de plages de dates [dateDebut, dateFin)
            $sql = "SELECT COALESCE(SUM(quantite),0) FROM reservations
                    WHERE tarif_id = ? AND statut IN ('validee','en_attente')
                      AND date_resa < ? AND date_depart > ?";
            $params = [$tarifId, $dateFin, $dateDebut];
        } else {
            // Créneau : seules les réservations VALIDÉES bloquent, avec 2h de marge après leur fin
            $sql = "SELECT COALESCE(SUM(quantite),0) FROM reservations
                    WHERE tarif_id = ? AND date_resa = ? AND statut = 'validee'
                      AND heure_debut < ? AND ADDTIME(heure_fin, '02:00:00') > ?";
            $params = [$tarifId, $dateDebut, $heureFin, $heureDebut];
        }
        if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $occupees = (int)$stmt->fetchColumn();
        return ($occupees + max(1, $quantiteDemandee)) <= $limite;
    }
}

if (!function_exists('creneaux_libres_du_jour')) {
    /**
     * Calcule les plages horaires encore libres pour un tarif donné, à une
     * date donnée, en tenant compte des réservations déjà VALIDÉES et de
     * leur marge de 2h. Plage d'ouverture supposée 07h-22h. Retourne un
     * tableau de chaînes lisibles, ex: ["07:00 - 09:00", "15:00 - 22:00"].
     */
    function creneaux_libres_du_jour(PDO $pdo, int $tarifId, string $date): array {
        $stmt = $pdo->prepare("
            SELECT heure_debut, heure_fin FROM reservations
            WHERE tarif_id = ? AND date_resa = ? AND statut = 'validee'
            ORDER BY heure_debut ASC
        ");
        $stmt->execute([$tarifId, $date]);
        $occupations = $stmt->fetchAll();

        $curseur = strtotime('07:00:00');
        $finJournee = strtotime('22:00:00');
        $libres = [];

        foreach ($occupations as $o) {
            $debutOccupe = strtotime($o['heure_debut']);
            $finOccupeAvecMarge = strtotime($o['heure_fin']) + 2 * 3600;
            if ($debutOccupe > $curseur) {
                $libres[] = date('H:i', $curseur) . ' - ' . date('H:i', $debutOccupe);
            }
            if ($finOccupeAvecMarge > $curseur) $curseur = $finOccupeAvecMarge;
        }
        if ($curseur < $finJournee) {
            $libres[] = date('H:i', $curseur) . ' - ' . date('H:i', $finJournee);
        }
        return $libres;
    }
}

if (!function_exists('periode_actuelle_debut')) {
    /** Premier jour de la période de bail en cours, selon sa périodicité. */
    function periode_actuelle_debut(string $typeBail): string {
        $mois = (int)date('n');
        $an   = (int)date('Y');
        switch ($typeBail) {
            case 'trimestriel': $moisDebut = intdiv($mois - 1, 3) * 3 + 1; break;
            case 'semestriel':  $moisDebut = intdiv($mois - 1, 6) * 6 + 1; break;
            case 'annuel':      $moisDebut = 1; break;
            default:            $moisDebut = $mois; // mensuel
        }
        return sprintf('%04d-%02d-01', $an, $moisDebut);
    }
}

if (!function_exists('limite_paiement')) {
    /**
     * Calcule la date limite de paiement (48h) à partir d'un instant donné.
     * Si l'échéance tombe un samedi ou un dimanche, elle est reportée au
     * lundi suivant à la même heure — le Palais étant fermé le week-end.
     */
    function limite_paiement(string $depart): int {
        $limite = strtotime($depart) + 48 * 3600;
        $jourSemaine = (int)date('N', $limite); // 6 = samedi, 7 = dimanche
        if ($jourSemaine === 6) {
            $limite = strtotime('+2 days', $limite);
        } elseif ($jourSemaine === 7) {
            $limite = strtotime('+1 day', $limite);
        }
        return $limite;
    }
}

if (!function_exists('annuler_reservations_concurrentes')) {
    /**
     * Appelée juste après l'encaissement d'une réservation validée : le
     * paiement départage définitivement les demandes concurrentes sur le
     * même espace / même créneau (ou même plage de séjour). Les autres
     * réservations validées mais encore non payées qui chevauchent sont
     * annulées automatiquement, et leur client notifié avec les créneaux
     * encore libres ce jour-là.
     *
     * @return array Liste des réservations annulées (pour information/log)
     */
    function annuler_reservations_concurrentes(PDO $pdo, int $reservationPayeeId): array {
        $r = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $r->execute([$reservationPayeeId]);
        $resa = $r->fetch();
        if (!$resa) return [];

        $estSejour = !empty($resa['date_depart']);

        if ($estSejour) {
            $sql = "SELECT r.id, r.user_id, r.tarif_id, r.date_resa, e.nom AS espace_nom
                    FROM reservations r JOIN espaces e ON e.id = r.espace_id
                    WHERE r.espace_id = ? AND r.id != ? AND r.statut = 'validee' AND r.statut_paiement != 'paye'
                      AND r.date_resa < ? AND r.date_depart > ?";
            $params = [$resa['espace_id'], $reservationPayeeId, $resa['date_depart'], $resa['date_resa']];
        } else {
            $sql = "SELECT r.id, r.user_id, r.tarif_id, r.date_resa, e.nom AS espace_nom
                    FROM reservations r JOIN espaces e ON e.id = r.espace_id
                    WHERE r.espace_id = ? AND r.id != ? AND r.statut = 'validee' AND r.statut_paiement != 'paye'
                      AND r.date_resa = ? AND r.heure_debut < ? AND ADDTIME(r.heure_fin, '02:00:00') > ?";
            $params = [$resa['espace_id'], $reservationPayeeId, $resa['date_resa'], $resa['heure_fin'], $resa['heure_debut']];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $concurrentes = $stmt->fetchAll();

        foreach ($concurrentes as $c) {
            $pdo->prepare("UPDATE reservations SET statut = 'annulee', notification_vue = 0 WHERE id = ?")->execute([$c['id']]);
            $creneaux = !$estSejour ? creneaux_libres_du_jour($pdo, $c['tarif_id'], $c['date_resa']) : [];
            notify('', 'reservation_impossible',
                "La salle « {$c['espace_nom']} » n'est plus disponible pour votre créneau demandé — une autre personne a réglé son paiement en premier." . ($creneaux ? ' Créneaux encore libres ce jour : ' . implode(', ', $creneaux) . '.' : ' Contactez-nous pour connaître les prochaines disponibilités.'),
                "mon-compte.php", (int)$c['user_id']
            );
            log_activity('reservation_annulee_conflit', 'reservations', "Réservation #{$c['id']} annulée automatiquement — paiement concurrent #$reservationPayeeId effectué en premier");
        }
        return $concurrentes;
    }
}



if (!function_exists('admin_nav')) {
    function admin_nav(): array {
        $role = $_SESSION['role'] ?? 'user';
        $all  = [
            'dashboard.php'    => ['fas fa-chart-pie',      'Tableau de bord', ['superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable']],
            'notifications.php'=> ['fas fa-bell',           'Notifications',   ['superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable']],
            'espaces.php'      => ['fas fa-building',       'Espaces',         ['superadmin','ministre','admin_espaces']],
            'reservations.php' => ['fas fa-calendar-check', 'Réservations',    ['superadmin','ministre','admin_espaces','admin_comptable']],
            'paiements.php'    => ['fas fa-cash-register',  'Paiements',       ['superadmin','ministre','admin_comptable']],
            'acomptes.php'     => ['fas fa-coins',          'Acomptes',        ['superadmin','ministre','admin_comptable']],
            'requisitions.php' => ['fas fa-landmark',       'Réquisitions',    ['superadmin','ministre','admin_comptable']],
            'baux.php'         => ['fas fa-file-signature', 'Baux',            ['superadmin','ministre','admin_comptable','admin_espaces']],
            'demandes-bail.php'=> ['fas fa-inbox',          'Demandes de bail',['superadmin','ministre','admin_comptable','admin_espaces']],
            'demandes-services.php'=> ['fas fa-concierge-bell','Demandes de services',['superadmin','ministre','admin_comptable','admin_espaces']],
            'jeunes-engages.php'=> ['fas fa-hand-fist',        'Jeunes engagés',  ['superadmin','ministre','admin_activites']],
            'guichet.php'      => ['fas fa-store',          'Guichet',         ['superadmin','admin_espaces','admin_comptable']],
            'activites.php'    => ['fas fa-star',           'Activités',       ['superadmin','ministre','admin_activites']],
            'personnalites.php'=> ['fas fa-user-tie',        'Personnalités',   ['superadmin','ministre','admin_activites']],
            'direction.php'    => ['fas fa-user-shield',     'Direction',       ['superadmin','ministre']],
            'formations.php'   => ['fas fa-graduation-cap',  'Formations',      ['superadmin','ministre','admin_activites']],
            'personnel.php'    => ['fas fa-id-badge',        'Personnel',       ['superadmin','ministre','admin_activites']],
            'messages.php'     => ['fas fa-envelope',       'Messages',        ['superadmin','ministre','admin_messages','admin_espaces','admin_activites']],
            'observations.php' => ['fas fa-eye',            'Observations',    ['superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable']],
            'activity.php'     => ['fas fa-history',        'Journal',         ['superadmin','ministre']],
            'users.php'        => ['fas fa-users',          'Utilisateurs',    ['superadmin','ministre']],
        ];
        $nav = [];
        foreach ($all as $href => [$icon, $label, $roles]) {
            if (in_array($role, $roles, true)) $nav[$href] = [$icon, $label];
        }
        return $nav;
    }
}

if (!function_exists('admin_nav_badges')) {
    /**
     * Compteurs contextuels affichés en pastille sur les liens de la sidebar
     * (ex: nombre de réservations en attente de validation, de paiements en
     * attente d'encaissement). Ce sont des comptages en direct — dès que
     * l'admin traite l'élément concerné, le nombre baisse tout seul.
     */
    function admin_nav_badges(): array {
        $badges = [];
        try {
            $pdo = db();
            $badges['reservations.php'] = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn();
            $badges['paiements.php']    = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'validee' AND statut_paiement != 'paye'")->fetchColumn();
            $badges['acomptes.php']     = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut_paiement = 'partiellement_paye'")->fetchColumn();
            $badges['requisitions.php'] = (int)$pdo->query("SELECT COUNT(*) FROM requisitions_ministerielles WHERE statut = 'en_attente_choix' AND choix_client IS NOT NULL")->fetchColumn();
            $stmtBaux = $pdo->prepare("
                SELECT COUNT(*) FROM espaces e
                WHERE e.gerant_externe IS NOT NULL AND e.gerant_externe != ''
                  AND NOT EXISTS (SELECT 1 FROM bail_paiements bp WHERE bp.espace_id = e.id AND bp.mois = ?)
            ");
            $stmtBaux->execute([date('Y-m-01')]);
            $badges['baux.php'] = (int)$stmtBaux->fetchColumn();
            $badges['demandes-bail.php'] = (int)$pdo->query("SELECT COUNT(*) FROM demandes_bail WHERE statut = 'en_attente'")->fetchColumn();
        } catch (Exception $e) {}
        return $badges;
    }
}

if (!function_exists('e')) {
    function e(?string $s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('ref_recu')) {
    /**
     * Référence lisible et unique d'un reçu de paiement (ex: REC-2026-000123),
     * basée sur l'ID auto-incrémenté de la table `paiements`. Affichée sur le
     * reçu client, dans les notifications, le journal d'audit et l'écran
     * comptable — toujours identique pour un même paiement, ce qui permet de
     * le retrouver instantanément en base en cas de contrôle.
     */
    function ref_recu(int $paiementId): string {
        return date('y') . '-' . str_pad((string)$paiementId, 3, '0', STR_PAD_LEFT) . '/DGPP-C';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('log_activity')) {
    function log_activity(string $action, string $module, string $details = ''): void {
        try {
            $u = current_user();
            if (!$u) return;
            db()->prepare("INSERT INTO activity_log (user_id, user_nom, action, module, details) VALUES (?,?,?,?,?)")
               ->execute([$u['id'], $u['nom_complet'], $action, $module, $details]);
        } catch (Exception $e) {}
    }
}

if (!function_exists('notify')) {
    function notify(string $role, string $type, string $message, string $lien = '', ?int $destinataireId = null): void {
        try {
            db()->prepare("INSERT INTO notifications (destinataire_role, destinataire_id, type, message, lien) VALUES (?,?,?,?,?)")
               ->execute([$role, $destinataireId, $type, $message, $lien]);
        } catch (Exception $e) {}
    }
}

if (!function_exists('count_notifications')) {
    function count_notifications(): int {
        $role = $_SESSION['role']    ?? '';
        $uid  = $_SESSION['user_id'] ?? 0;
        try {
            $s = db()->prepare("SELECT COUNT(*) FROM notifications WHERE lu = 0 AND (destinataire_role = ? OR destinataire_id = ?)");
            $s->execute([$role, $uid]);
            return (int)$s->fetchColumn();
        } catch (Exception $e) { return 0; }
    }
}

if (!function_exists('expirer_reservations_non_payees')) {
    /**
     * Annule automatiquement les réservations validées mais toujours
     * impayées 48h après leur validation — le client n'est pas venu payer
     * dans le délai, le créneau/la chambre redevient disponible pour
     * quelqu'un d'autre. Appelée à chaque chargement des pages admin
     * concernées (pas de vrai cron nécessaire pour ce volume).
     */
    function expirer_reservations_non_payees(): void {
        $pdo = db();
        try {
            $stmt = $pdo->query("
                SELECT r.id, r.user_id, r.date_resa, r.date_validation, e.nom AS espace_nom
                FROM reservations r
                JOIN espaces e ON e.id = r.espace_id
                WHERE r.statut = 'validee'
                  AND r.statut_paiement != 'paye'
                  AND r.date_validation IS NOT NULL
            ");
            $candidates = $stmt->fetchAll();
            $expirees = array_filter($candidates, fn($r) => limite_paiement($r['date_validation']) < time());
        } catch (Exception $e) { return; }
        if (!$expirees) return;

        foreach ($expirees as $r) {
            $pdo->prepare("
                UPDATE reservations
                SET statut = 'expiree', note_admin = CONCAT(COALESCE(note_admin,''), '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.')
                WHERE id = ?
            ")->execute([$r['id']]);

            notify('admin_espaces', 'reservation_expiree',
                "Réservation #{$r['id']} pour «{$r['espace_nom']}» annulée automatiquement (48h sans paiement)",
                "reservations.php"
            );
            notify('', 'reservation_expiree',
                "Votre demande pour « {$r['espace_nom']} » du " . date('d/m/Y', strtotime($r['date_resa'])) . " a été annulée automatiquement : le paiement n'a pas été effectué dans le délai de 48h. Vous pouvez faire une nouvelle demande si le créneau vous intéresse toujours.",
                "espaces.php", (int)$r['user_id']
            );
            log_activity('reservation_expiree', 'reservations', "Réservation #{$r['id']} annulée automatiquement (délai 48h dépassé)");
        }
    }
}