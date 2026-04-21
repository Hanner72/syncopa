<?php
// includes/fest_tabs.php
// Horizontale Tab-Navigation für Fest-Unterseiten
// Erwartet: $festId (int), $fest (array mit 'name'), $currentPage (string)

$tabs = [
    'fest_detail'     => ['icon' => 'bi-speedometer2',    'label' => 'Übersicht',    'url' => 'fest_detail.php?id='          . $festId],
    'fest_stationen'  => ['icon' => 'bi-geo-alt',         'label' => 'Stationen',    'url' => 'fest_stationen.php?fest_id='  . $festId],
    'fest_mitarbeiter'=> ['icon' => 'bi-people',          'label' => 'Mitarbeiter',  'url' => 'fest_mitarbeiter.php?fest_id='. $festId],
    'fest_dienstplan' => ['icon' => 'bi-calendar3',       'label' => 'Dienstplan',   'url' => 'fest_dienstplan.php?fest_id=' . $festId],
    'fest_einkauefe'  => ['icon' => 'bi-cart3',           'label' => 'Einkäufe',     'url' => 'fest_einkauefe.php?fest_id='  . $festId],
    'fest_vertraege'  => ['icon' => 'bi-file-earmark-text','label' => 'Verträge',    'url' => 'fest_vertraege.php?fest_id='  . $festId],
    'fest_todos'      => ['icon' => 'bi-check2-square',   'label' => 'Todos',        'url' => 'fest_todos.php?fest_id='      . $festId],
    'fest_abrechnung' => ['icon' => 'bi-calculator',      'label' => 'Abrechnung',   'url' => 'fest_abrechnung.php?fest_id=' . $festId],
];

// Seiten die zu einem Tab gehören (für aktiv-Erkennung)
$tabPages = [
    'fest_detail'      => ['fest_detail'],
    'fest_stationen'   => ['fest_stationen', 'fest_station_bearbeiten'],
    'fest_mitarbeiter' => ['fest_mitarbeiter', 'fest_mitarbeiter_bearbeiten'],
    'fest_dienstplan'  => ['fest_dienstplan', 'fest_dienstplan_bearbeiten'],
    'fest_einkauefe'   => ['fest_einkauefe', 'fest_einkauf_bearbeiten'],
    'fest_vertraege'   => ['fest_vertraege', 'fest_vertrag_bearbeiten'],
    'fest_todos'       => ['fest_todos', 'fest_todo_bearbeiten'],
    'fest_abrechnung'  => ['fest_abrechnung'],
];
?>
<div class="fest-tabs-header mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
            <a href="feste.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Alle Feste</a>
            <h4 class="mb-0 mt-1"><i class="bi bi-stars"></i> <?php echo htmlspecialchars($fest['name']); ?></h4>
        </div>
        <?php if (!empty($fest['datum_von'])): ?>
        <div class="text-muted small text-end">
            <?php echo date('d.m.Y', strtotime($fest['datum_von'])); ?>
            <?php if (!empty($fest['datum_bis']) && $fest['datum_bis'] !== $fest['datum_von']): ?>
            – <?php echo date('d.m.Y', strtotime($fest['datum_bis'])); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <ul class="nav nav-tabs">
        <?php foreach ($tabs as $key => $tab): ?>
        <?php $isActive = in_array($currentPage, $tabPages[$key] ?? [$key]); ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo $tab['url']; ?>">
                <i class="bi <?php echo $tab['icon']; ?>"></i>
                <span class="d-none d-md-inline"> <?php echo $tab['label']; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
