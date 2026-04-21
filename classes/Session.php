<?php
// classes/Session.php

class Session {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            session_start();
        }
    }
    
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        self::start();
        session_destroy();
        $_SESSION = [];
    }
    
    public static function isLoggedIn() {
        return self::has('user_id');
    }
    
    public static function getUserId() {
        return self::get('user_id');
    }
    
    public static function getUsername() {
        return self::get('username');
    }
    
    public static function getRole() {
        return self::get('rolle', 'mitglied');
    }

    /** Gibt true zurück wenn der Benutzer Admin-Rechte hat (mind. eine Admin-Rolle) */
    public static function isAdmin(): bool {
        // Neue Session: ist_admin gesetzt
        if (self::has('ist_admin')) return (bool)self::get('ist_admin', false);
        // Fallback für alte Sessions (vor Mehrfachrollen-Update)
        return self::get('rolle') === 'admin';
    }

    /** Alle Rollen-IDs des eingeloggten Benutzers */
    public static function getRollenIds(): array {
        return self::get('rollen_ids', []);
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function setFlashMessage($type, $message) {
        self::set('flash_message', ['type' => $type, 'message' => $message]);
    }
    
    public static function getFlashMessage() {
        if (self::has('flash_message')) {
            $message = self::get('flash_message');
            self::remove('flash_message');
            return $message;
        }
        return null;
    }
    
    public static function checkPermission($modul, $aktion = 'lesen'): bool {
        // Admins haben immer alle Rechte
        if (self::isAdmin()) return true;

        $rollenIds = self::getRollenIds();

        // Fallback für alte Sessions: rolle_id aus benutzer-Tabelle direkt abfragen
        if (empty($rollenIds) && self::isLoggedIn()) {
            $db = Database::getInstance();
            $rows = $db->fetchAll(
                "SELECT rolle_id FROM benutzer_rollen WHERE benutzer_id = ?",
                [self::getUserId()]
            );
            $rollenIds = array_column($rows, 'rolle_id');
        }

        if (empty($rollenIds)) return false;

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($rollenIds), '?'));
        // Prüft ob IRGENDEINE der zugewiesenen Rollen das Recht hat
        $sql = "SELECT MAX(b.`{$aktion}`) as hat_recht
                FROM berechtigungen b
                JOIN rollen r ON r.name = b.rolle
                WHERE r.id IN ({$placeholders}) AND b.modul = ?";
        $params = array_merge(array_values($rollenIds), [$modul]);
        $result = $db->fetchOne($sql, $params);

        return $result && $result['hat_recht'] == 1;
    }

    public static function requirePermission($modul, $aktion = 'lesen') {
        if (!self::checkPermission($modul, $aktion)) {
            self::setFlashMessage('danger', 'Keine Berechtigung für diese Aktion!');
            header('Location: index.php');
            exit;
        }
    }
}
