<?php
// admin/config.php
session_start();

define('DB_FILE', __DIR__ . '/../data/tramtrithuc.db');
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'tramtrithuc2026'); // Admin: chỉ vào trực tiếp /admin/login.php

if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

$DEFAULT_CATEGORIES = [
    ['Sinh viên', '🎓', 10],
    ['Ngoại ngữ', '🗣️', 20],
    ['Kỹ năng mềm', '💬', 30],
    ['Kỹ năng sống', '🌱', 40],
    ['Văn phòng', '💼', 50],
    ['Thiết kế', '🎨', 60],
    ['Video Editor', '🎬', 70],
    ['Chụp Ảnh & Quay Phim', '📷', 80],
    ['Lập trình', '💻', 90],
    ['Marketing', '📣', 100],
    ['TikTok', '🎵', 110],
    ['Youtube', '▶️', 120],
    ['Facebook', '📘', 130],
    ['AI - Automation', '🤖', 140],
    ['Kinh doanh', '📈', 150],
    ['Kiếm tiền online', '💰', 160],
    ['Đầu tư', '📊', 170],
    ['Khác', '📦', 999],
];

try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        category TEXT NOT NULL,
        author TEXT DEFAULT 'Cổng Tri Thức',
        short_desc TEXT,
        size TEXT,
        shopee_url TEXT,
        drive_url TEXT NOT NULL,
        type TEXT DEFAULT 'Tài liệu',
        votes INTEGER DEFAULT 0,
        views INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 0,
        status INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        icon TEXT DEFAULT '📁',
        sort_order INTEGER DEFAULT 0,
        status INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_likes (
        user_id INTEGER NOT NULL,
        document_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, document_id)
    )");

    // Migrations
    $cols = $pdo->query("PRAGMA table_info(documents)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('views', $colNames, true)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN views INTEGER DEFAULT 0");
    }
    if (!in_array('sort_order', $colNames, true)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN sort_order INTEGER DEFAULT 0");
    }
    if (!in_array('category2', $colNames, true)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN category2 TEXT DEFAULT ''");
    }
    if (!in_array('shopee_clicks', $colNames, true)) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN shopee_clicks INTEGER DEFAULT 0");
    }

    // Seed categories nếu trống
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($cnt === 0) {
        $ins = $pdo->prepare("INSERT INTO categories (name, icon, sort_order) VALUES (?,?,?)");
        foreach ($DEFAULT_CATEGORIES as $c) {
            $ins->execute($c);
        }
    }

    // Load danh mục từ DB
    $CATEGORIES = $pdo->query(
        "SELECT name FROM categories WHERE status = 1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (empty($CATEGORIES)) {
        $CATEGORIES = array_column($DEFAULT_CATEGORIES, 0);
    }

} catch (Exception $e) {
    die('Lỗi kết nối database: ' . $e->getMessage());
}

$TYPES = ['Khóa học', 'Tài liệu', 'Template'];

/** Chuyển tiêu đề → slug URL gọn (bỏ từ thừa, giới hạn độ dài) */
function slugify_title($title) {
    $title = trim((string)$title);
    $map = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
        'ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
        'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
        'đ'=>'d',
        'À'=>'a','Á'=>'a','Ạ'=>'a','Ả'=>'a','Ã'=>'a','Â'=>'a','Ầ'=>'a','Ấ'=>'a','Ậ'=>'a','Ẩ'=>'a','Ẫ'=>'a',
        'Ă'=>'a','Ằ'=>'a','Ắ'=>'a','Ặ'=>'a','Ẳ'=>'a','Ẵ'=>'a',
        'È'=>'e','É'=>'e','Ẹ'=>'e','Ẻ'=>'e','Ẽ'=>'e','Ê'=>'e','Ề'=>'e','Ế'=>'e','Ệ'=>'e','Ể'=>'e','Ễ'=>'e',
        'Ì'=>'i','Í'=>'i','Ị'=>'i','Ỉ'=>'i','Ĩ'=>'i',
        'Ò'=>'o','Ó'=>'o','Ọ'=>'o','Ỏ'=>'o','Õ'=>'o','Ô'=>'o','Ồ'=>'o','Ố'=>'o','Ộ'=>'o','Ổ'=>'o','Ỗ'=>'o',
        'Ơ'=>'o','Ờ'=>'o','Ớ'=>'o','Ợ'=>'o','Ở'=>'o','Ỡ'=>'o',
        'Ù'=>'u','Ú'=>'u','Ụ'=>'u','Ủ'=>'u','Ũ'=>'u','Ư'=>'u','Ừ'=>'u','Ứ'=>'u','Ự'=>'u','Ử'=>'u','Ữ'=>'u',
        'Ỳ'=>'y','Ý'=>'y','Ỵ'=>'y','Ỷ'=>'y','Ỹ'=>'y',
        'Đ'=>'d',
    ];
    $s = strtr($title, $map);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');

    // Bỏ từ đệm / ít ý nghĩa để link gọn hơn
    $stop = [
        'file', 'pdf', 'doc', 'docx', 'zip', 'rar', 'mp4', 'full', 'free', 'fullfree',
        'tai', 'lieu', 'tailieu', 'khoa', 'hoc', 'khoahoc', 'ban', 'moi', 'nhat',
        'va', 'cua', 'cho', 'voi', 'cac', 'nhung', 'mot', 'nhung', 'the', 'trong',
        'phien', 'ban', 'version', 'ver', 'tap', 'bo', 'tron', 'botron', 'download',
        'cap', 'nhat', 'update', 'official', 'chinh', 'thuc'
    ];
    $parts = array_values(array_filter(explode('-', $s), function ($p) use ($stop) {
        if ($p === '' || strlen($p) < 2) return false;
        if (in_array($p, $stop, true)) return false;
        // bỏ token chỉ toàn số dài (năm, mã rác) nhưng giữ hsk1, a1...
        return true;
    }));

    // Giữ tối đa 5 từ khóa đầu
    if (count($parts) > 5) {
        $parts = array_slice($parts, 0, 5);
    }
    $s = implode('-', $parts);

    // Cắt tối đa ~36 ký tự (không cắt giữa từ)
    $max = 36;
    if (strlen($s) > $max) {
        $s = substr($s, 0, $max);
        $s = preg_replace('/-[^-]*$/', '', $s); // bỏ mảnh từ cuối bị cắt
        $s = rtrim($s, '-');
    }

    return $s !== '' ? $s : 'tai-lieu';
}

/**
 * Slug public gọn: theo tiêu đề rút gọn; trùng slug → thêm -id
 * @param int $id
 * @param string|null $title
 */
function public_doc_slug($id, $title = null) {
    global $pdo;
    $id = (int)$id;
    if ($title === null || $title === '') {
        try {
            $st = $pdo->prepare("SELECT title FROM documents WHERE id = ?");
            $st->execute([$id]);
            $title = (string)($st->fetchColumn() ?: '');
        } catch (Exception $e) {
            $title = '';
        }
    }
    $base = slugify_title($title);
    $same = 0;
    try {
        $rows = $pdo->query("SELECT id, title FROM documents")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (slugify_title($r['title']) === $base) {
                $same++;
            }
        }
    } catch (Exception $e) {
        $same = 1;
    }
    if ($same > 1) {
        return $base . '-' . $id;
    }
    return $base;
}

/** URL public của tài liệu (share theo tiêu đề) */
function public_doc_url($id, $title = null) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
    if ($base === '' || $base === '\\') $base = '';
    $slug = public_doc_slug($id, $title);
    return $scheme . '://' . $host . $base . '/index.html?doc=' . rawurlencode($slug);
}
