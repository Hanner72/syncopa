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
$zustand = $_POST['zustand'] ?? null;
$bemerkungen = trim($_POST['bemerkungen'] ?? '') ?: null;
$redirect = $_POST['redirect'] ?? 'list';

if (!$uniformId) {
    Session::setFlashMessage('danger', 'Uniform-ID fehlt.');
    header('Location: uniformen.php');
    exit;
}

try {
    $uniformObj = new Uniform();
    
    // Prüfen ob Uniform ausgegeben ist
    $uniform = $uniformObj->getById($uniformId);
    if (!$uniform) {
        throw new Exception('Uniformteil nicht gefunden.');
    }
    if (!$uniform['mitglied_id']) {
        throw new Exception('Uniformteil ist nicht ausgegeben.');
    }
    
    $uniformObj->zuruecknehmen($uniformId, $zustand, $bemerkungen);
    Session::setFlashMessage('success', 'Uniformteil erfolgreich zurückgenommen.');
    
} catch (Exception $e) {
    Session::setFlashMessage('danger', $e->getMessage());
}

if ($redirect === 'detail') {
    header('Location: uniform_detail.php?id=' . $uniformId);
} else {
    header('Location: uniformen.php');
}
exit;
