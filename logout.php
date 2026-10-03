<?php
require_once __DIR__ . '/includes/auth.php';
Auth::logAction('LOGOUT', 'auth', $_SESSION['user_id'] ?? null);
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
