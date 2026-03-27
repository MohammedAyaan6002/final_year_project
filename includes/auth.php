<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $baseUrl = APP_BASE_URL;
        header('Location: ' . $baseUrl . '/pages/login.php');
        exit;
    }
}

function get_current_user_data() {
    if (is_logged_in()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

function logout() {
    session_destroy();
    $baseUrl = APP_BASE_URL;
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}
?>
