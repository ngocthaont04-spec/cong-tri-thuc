<?php
// admin/auth.php
require_once 'config.php';

function require_login() {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function is_logged_in() {
    return !empty($_SESSION['admin_logged_in']);
}
