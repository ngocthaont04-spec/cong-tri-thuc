<?php
require_once __DIR__ . '/../auth.php';
require_login();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin' ?> — Cổng Tri Thức</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="wp-admin">
<div id="wpwrap">
    <div id="adminmenumain">
        <div id="adminmenuback"></div>
        <div id="adminmenuwrap">
            <ul id="adminmenu">
                <li class="wp-menu-separator"></li>
                <li class="menu-top <?= $current_page === 'index.php' ? 'current' : '' ?>">
                    <a href="index.php"><div class="wp-menu-name">Dashboard</div></a>
                </li>
                <li class="menu-top <?= in_array($current_page, ['documents.php','document-form.php']) ? 'current' : '' ?>">
                    <a href="documents.php"><div class="wp-menu-name">Tài liệu</div></a>
                </li>
                <li class="menu-top <?= $current_page === 'categories.php' ? 'current' : '' ?>">
                    <a href="categories.php"><div class="wp-menu-name">Danh mục</div></a>
                </li>
                <li class="menu-top <?= $current_page === 'reports.php' ? 'current' : '' ?>">
                    <a href="reports.php"><div class="wp-menu-name">Link hỏng</div></a>
                </li>
                <li class="menu-top">
                    <a href="../" target="_blank"><div class="wp-menu-name">Xem trang chủ</div></a>
                </li>
                <li class="menu-top">
                    <a href="logout.php"><div class="wp-menu-name">Đăng xuất</div></a>
                </li>
            </ul>
        </div>
    </div>

    <div id="wpcontent">
        <div id="wpbody">
            <div id="wpbody-content">
