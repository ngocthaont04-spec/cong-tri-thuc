<?php
require_once 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    }
}

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập — Cổng Tri Thức Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-header">
            <h1>Cổng Tri Thức</h1>
            <p>Quản trị hệ thống</p>
        </div>
        <?php if ($error): ?>
            <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <p>
                <label>Tên đăng nhập</label>
                <input type="text" name="username" required autofocus>
            </p>
            <p>
                <label>Mật khẩu</label>
                <input type="password" name="password" required>
            </p>
            <p class="submit">
                <button type="submit" class="button button-primary">Đăng nhập</button>
            </p>
        </form>
    </div>
</body>
</html>
