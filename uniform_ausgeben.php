<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'schreiben');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: uniformen.php');
    exit;
}

$uniformId = $_POST['uniform_id'] ?? null;
$mitgliedId = $_POST['mitglied_id'] ?? null;
$bemerkungen = trim($_POST['bemerkungen'] ?? '') ?: null;
$redirect = $_POST['redirect'] ?? 'list';

if (!$uniformId || !$mitgliedId) {
    Session::setFlashMessage('danger', 'Bitte alle Pflichtfelder ausfüllen.');
    header('Location: uniformen.php');
    exit;
}

try {
    $uniformObj = new Uniform();
    
    // Prüfen ob Uniform noch verfügbar
    $uniform = $uniformObj->getById($uniformId);
    if (!$uniform) {
        throw new Exception('Uniformteil nicht gefunden.');
    }
    if ($uniform['mitglied_id']) {
        throw new Exception('Uniformteil ist bereits ausgegeben.');
    }
    
    $uniformObj->ausgeben($uniformId, $mitgliedId, $bemerkungen);
    Session::setFlashMessage('success', 'Uniformteil erfolgreich ausgegeben.');
    
} catch (Exception $e) {
    Session::setFlashMessage('danger', $e->getMessage());
}

if ($redirect === 'detail') {
    header('Location: uniform_detail.php?id=' . $uniformId);
} else {
    header('Location: uniformen.php');
}
exit;
