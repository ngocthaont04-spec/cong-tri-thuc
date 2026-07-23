# Cổng Tri Thức

Thư viện tài liệu (PHP + SQLite). Chạy local bằng XAMPP hoặc deploy online từ GitHub.

## ⚠️ GitHub Pages **không chạy** được project này

GitHub Pages chỉ host **HTML/CSS/JS tĩnh**.  
Site này cần **PHP + SQLite** (admin, API, đăng nhập…) → phải dùng **hosting PHP** hoặc **Docker**.

| Nơi | Có chạy được? |
|-----|----------------|
| XAMPP localhost | ✅ |
| GitHub (xem code) | Chỉ lưu code |
| GitHub Pages | ❌ Không có PHP |
| Render / Railway (Docker) | ✅ |

---

## Chạy local (XAMPP)

1. Copy project vào `C:\xampp\htdocs\tramtrithuc`
2. Bật **Apache** trong XAMPP
3. Mở: http://localhost/tramtrithuc/
4. Admin: http://localhost/tramtrithuc/admin/login.php  
   - User: `admin`  
   - Pass: đổi ngay trong `admin/config.php`

---

## Đưa lên website public (khuyên dùng: Render)

### Cách A — Render (miễn phí, nối GitHub)

1. Vào https://render.com → Sign up bằng **GitHub**
2. **New +** → **Web Service**
3. Connect repo: `ngocthaont04-spec/cong-tri-thuc`
4. Cấu hình:
   - **Runtime:** Docker  
   - **Branch:** main  
   - **Instance type:** Free  
5. **Create Web Service** → đợi build  
6. Nhận link dạng: `https://cong-tri-thuc-xxxx.onrender.com`

> Gói free: máy “ngủ” khi không ai vào ~15 phút; lần mở sau có thể chờ 30–60s.  
> Database SQLite trên free có thể mất khi redeploy (nên backup).

### Cách B — Railway

1. https://railway.app → Login GitHub  
2. **New Project** → **Deploy from GitHub repo**  
3. Chọn `cong-tri-thuc`  
4. Railway nhận Dockerfile → Deploy  
5. Generate domain public

### Cách C — Hosting PHP cPanel (InfinityFree, hosting Việt…)

1. Upload toàn bộ file (trừ `.git`) lên `public_html`  
2. Bật PHP 8.x  
3. Chmod thư mục `data` = 775  
4. Mở domain của bạn

---

## Bảo mật trước khi public

Trong `admin/config.php` đổi ngay:

```php
define('ADMIN_PASS', 'mat-khau-manh-cua-ban');
```

---

## Cấu trúc

- `index.html` — giao diện web  
- `api.php` / `user_api.php` — API  
- `admin/` — quản trị  
- `data/` — SQLite (local, không commit lên Git)
