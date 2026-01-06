<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'loeschen');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: uniformen.php');
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id) {
    Session::setFlashMessage('danger', 'Uniform-ID fehlt.');
    header('Location: uniformen.php');
    exit;
}

try {
    $uniformObj = new Uniform();
    $uniformObj->delete($id);
    Session::setFlashMessage('success', 'Uniformteil erfolgreich gelöscht.');
} catch (Exception $e) {
    Session::setFlashMessage('danger', $e->getMessage());
}

header('Location: uniformen.php');
exit;
