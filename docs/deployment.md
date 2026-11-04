# Deployment

## Apache (recommended for multiple TVs)

Point a virtual host at `backend/public` (keeps `app/`, `vendor/`, `.env` and `storage/` outside the web root):

```apache
<VirtualHost *:80>
    ServerName visitors.example.com
    DocumentRoot "C:/xampp/htdocs/visitor-management/backend/public"
    <Directory "C:/xampp/htdocs/visitor-management/backend/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Enable `mod_rewrite` (XAMPP has it). The included `.htaccess` sends every non-file request to `index.php`, which serves the API (`/api/*`) or the React app (client-side routes keep working on refresh).

## HTTPS (required for live camera on phones, and for production)

* Public server: use Let's Encrypt (certbot / win-acme on Windows) or your reverse proxy.
* Office-LAN only: create a local certificate with [mkcert](https://github.com/FiloSottile/mkcert), install its root CA on the phones/TVs you control, or put the app behind a Cloudflare Tunnel / internal CA.
* Then set in `.env`: `SESSION_SECURE_COOKIE=true`, `APP_URL`/`FRONTEND_URL` to the `https://` address (HSTS header is sent automatically), and update the QR URL in Admin → QR code.

## Production checklist

* `APP_ENV=production` (hides internal error messages).
* MongoDB: enable authentication, bind to localhost or a private network, use a user limited to the `visitor_management` database, and put the credentials only in `backend/.env` (`MONGODB_URI=mongodb://user:pass@host:27017/?authSource=visitor_management`).
* `.env` must never be committed (`.gitignore` covers it).
* PHP: `display_errors=Off`, `expose_php=Off`, upload/post limits as in the installation guide; keep `error_log` somewhere not web-accessible.
* Time: keep the server clock correct (NTP). TV timers are computed from the server's UTC time only.

## File permissions

`backend/storage` (or `STORAGE_PATH`) must be writable by the web-server user and **not** served by the web server (it is outside `public/` and has a deny-all `.htaccess` as a backup). Windows: grant the Apache service account *Modify* on that folder. Linux: `chown -R www-data:www-data storage && chmod -R 750 storage`.

## Retention and deletion

* Each visitor gets `retention_expires_at = registered + visitor_retention_days` (Admin → Settings; `0` = keep forever).
* When it passes, the **record and the photo file are deleted**. This happens (a) automatically at most once an hour, triggered by TV polling, and (b) by `php scripts/purge-expired.php`, which you can schedule daily:
  `schtasks /Create /SC DAILY /ST 03:00 /TN "VisitorPurge" /TR "C:\xampp\php\php.exe C:\xampp\htdocs\visitor-management\backend\scripts\purge-expired.php"`
* The visitor currently on screen is never purged. Admins can delete any record (and its file) at any time.
* Changing the retention days affects visitors registered afterwards.

## Backups

* Database: `mongodump --uri="mongodb://127.0.0.1:27017" --db=visitor_management --out=D:\backups\vm-%DATE%`
* Files: copy the `storage` folder (visitor photos + gallery) on the same schedule. Restore with `mongorestore` and copy the folder back.
* Backups contain personal data — encrypt them and apply the same retention policy.

## Updating

```powershell
git pull
cd backend ; composer install --no-dev --optimize-autoloader ; php scripts\setup-indexes.php
cd ..\frontend ; npm install ; npm run build
```
