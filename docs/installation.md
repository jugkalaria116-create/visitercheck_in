# Installation (Windows + XAMPP)

## 1. Prerequisites

| Item | Notes |
|---|---|
| XAMPP with **PHP 8.2+** | https://www.apachefriends.org |
| **MongoDB Community Server** 6/7/8 | https://www.mongodb.com/try/download/community — install as a Windows service (listens on 127.0.0.1:27017). MongoDB Atlas also works. |
| **MongoDB PHP extension** | Download `php_mongodb.dll` for *your* PHP version, thread-safe (TS), x64, from https://pecl.php.net/package/mongodb (Windows DLL links). Copy it to `C:\xampp\php\ext\`. |
| **Composer** | https://getcomposer.org |
| **Node.js 18+** | https://nodejs.org (only to build the frontend) |

## 2. php.ini (`C:\xampp\php\php.ini`)

```ini
extension=mongodb
extension=gd
extension=fileinfo
extension=curl
extension=exif        ; fixes phone-photo rotation
upload_max_filesize=10M
post_max_size=60M     ; gallery uploads up to 20 images at once
memory_limit=512M
```
Restart Apache (if used), then check: `php -m | findstr /i "mongodb gd fileinfo"`.

## 3. Backend

```powershell
cd C:\xampp\htdocs\visitor-management\backend
composer install
copy .env.example .env
notepad .env
```
Edit `.env` (see `.env.example`). Minimum: `MONGODB_URI`, `APP_URL`, `FRONTEND_URL`. If phones will use the site, set `APP_URL` / `FRONTEND_URL` to the address phones can reach (e.g. `https://visitors.example.com`) — **not localhost**.

```powershell
php scripts\setup-indexes.php   # prints "connection OK", creates indexes, seeds settings
```

## 4. First administrator

```powershell
php scripts\create-admin.php
# or non-interactive:
php scripts\create-admin.php --username=admin --password="a-long-passphrase-here"
# reset a password later:
php scripts\create-admin.php --username=admin --password="new-passphrase" --reset
```
Password minimum is 12 characters. There is no public admin sign-up.

## 5. Frontend

```powershell
cd ..\frontend
npm install
npm run build        # output goes to backend\public (index.html + assets)
```

## 6. Run

```powershell
cd ..\backend
php -S 0.0.0.0:8000 -t public public\index.php
```
* Landing: http://localhost:8000
* Registration: http://localhost:8000/register
* TV display: http://localhost:8000/display
* Admin: http://localhost:8000/admin

`php -S` handles one request at a time on Windows — fine for trying things out, **use Apache for real multi-TV use** (`docs/deployment.md`).

## 7. Configure the public URL / QR code

Admin → **QR code** → set the *Registration URL* to the address phones can open (e.g. `https://visitors.example.com/register`), then download/print the poster. The URL lives in MongoDB (`settings.registration_url`) and can be changed at any time without regenerating anything else.

## 8. Connect TVs

1. On each TV, open the browser and go to `https://<your-server>/display?name=Lobby` (use a different `name` per TV).
2. Press **F** or double-click for full screen. (Many smart-TV browsers have their own full-screen option.)
3. Admin → **TV displays** shows each TV as *Online* within ~10 s. A TV is *Recently disconnected* for 10 minutes after its last heartbeat, then *Offline*.

## 9. Live camera needs HTTPS

Browsers only allow `getUserMedia` on **HTTPS or localhost**. On `http://192.168.x.x` the in-page camera is blocked; the visitor sees a message and the **Upload** button still works (on phones it offers "Take photo" natively). To enable the live camera on phones, serve the site over HTTPS (see deployment guide).
