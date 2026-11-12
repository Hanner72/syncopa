<?php
// includes/anwesenheit_register.php
// Karten „Zusagen“ / „Absagen“ nach Register – genutzt von ausrueckung_detail.php und api/anwesenheit_liste.php (Live-Aktualisierung)

if (!function_exists('anwesenheitNachRegister')) {

    /**
     * Mitglieder einer Ausrückung mit einem bestimmten Status, sortiert nach Register und Name
     */
    function anwesenheitNachRegister(int $ausrueckungId, string $status): array {
        return Database::getInstance()->fetchAll(
            "SELECT m.vorname, m.nachname, m.id as mitglied_id, r.name as register_name, r.sortierung, a.grund
             FROM anwesenheit a
             JOIN mitglieder m ON a.mitglied_id = m.id
             LEFT JOIN register r ON m.register_id = r.id
             INNER JOIN (
                 SELECT mitglied_id, MAX(id) as max_id
                 FROM anwesenheit WHERE ausrueckung_id = ?
                 GROUP BY mitglied_id
             ) latest ON a.id = latest.max_id AND a.mitglied_id = latest.mitglied_id
             WHERE a.ausrueckung_id = ? AND a.status = ?
             ORDER BY r.sortierung, r.name, m.nachname, m.vorname",
            [$ausrueckungId, $ausrueckungId, $status]
        );
    }

    /**
     * HTML der Karte „Zusagen“ ($art = 'zugesagt') oder „Absagen“ ($art = 'abgesagt').
     * Ohne Einträge wird die Karte ausgeblendet ausgegeben, damit die Live-Aktualisierung sie einblenden kann.
     */
    function anwesenheitRegisterKarte(string $art, array $zeilen): string {
        $zusage = $art === 'zugesagt';
        $farbe  = $zusage ? 'success' : 'danger';
        $titel  = $zusage ? 'Zusagen' : 'Absagen';
        $icon   = $zusage ? 'bi-person-check' : 'bi-person-x';
        $punkt  = $zusage ? 'bi-check-circle-fill' : 'bi-x-circle-fill';

        $gruppen = [];
        foreach ($zeilen as $m) {
            $gruppen[$m['register_name'] ?? 'Ohne Register'][] = $m;
        }

        $h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        ob_start(); ?>
        <div class="card mb-3 border-<?php echo $farbe; ?><?php echo $zeilen ? '' : ' d-none'; ?>" id="anwKarte-<?php echo $art; ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi <?php echo $icon; ?> text-<?php echo $farbe; ?>"></i> <?php echo $titel; ?></h5>
                <span class="badge bg-<?php echo $farbe; ?>"><?php echo count($zeilen); ?></span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($gruppen as $registerName => $mitglieder): ?>
                <div class="px-3 pt-2 pb-1">
                    <small class="text-uppercase text-muted fw-semibold" style="font-size:0.7rem;letter-spacing:.05em;">
                        <?php echo $h($registerName); ?>
                    </small>
                    <ul class="list-unstyled mb-2 mt-1">
                        <?php foreach ($mitglieder as $m): ?>
                        <li class="py-1 border-bottom" data-mitglied-id="<?php echo (int)$m['mitglied_id']; ?>">
                            <i class="bi <?php echo $punkt; ?> text-<?php echo $farbe; ?> me-1" style="font-size:.8rem;"></i>
                            <small><?php echo $h($m['vorname'] . ' ' . $m['nachname']); ?></small>
                            <?php if (!$zusage && !empty($m['grund'])): ?>
                            <br><small class="text-muted ms-3"><i class="bi bi-chat-left-text"></i> <?php echo $h($m['grund']); ?></small>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
