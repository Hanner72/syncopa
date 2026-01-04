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
    
    public static function checkPermission($modul, $aktion = 'lesen') {
        $rolle = self::getRole();
        
        $db = Database::getInstance();
        $sql = "SELECT {$aktion} FROM berechtigungen WHERE rolle = ? AND modul = ?";
        $result = $db->fetchOne($sql, [$rolle, $modul]);
        
        return $result && $result[$aktion] == 1;
    }
    
    public static function requirePermission($modul, $aktion = 'lesen') {
        if (!self::checkPermission($modul, $aktion)) {
            self::setFlashMessage('danger', 'Keine Berechtigung für diese Aktion!');
            header('Location: index.php');
            exit;
        }
    }
}
