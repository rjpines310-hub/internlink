

# Deployment Guide — Capstone Web System

This file documents step-by-step instructions to deploy the Capstone web system on a typical LAMP/XAMPP stack (Windows local dev or Linux server).

Important workspace files referenced below:
- [db.php](db.php)
- [capstone_schema_rjpines310.sql](capstone_schema_rjpines310.sql)
- [login.php](login.php)
- [fetch_dashboard_data.php](fetch_dashboard_data.php)
- [fetch_resume_summary.php](fetch_resume_summary.php)
- [vendor/phpmailer/phpmailer/src/PHPMailer.php](vendor/phpmailer/phpmailer/src/PHPMailer.php)
- [vendor/composer/platform_check.php](vendor/composer/platform_check.php)
- [MESSAGING_IMPROVEMENTS_GUIDE.md](MESSAGING_IMPROVEMENTS_GUIDE.md)
- [TODO.md](TODO.md)

Prerequisites
1. Web server with PHP 7+ (XAMPP recommended for local development).
2. MySQL/MariaDB.
3. Composer (for dependency management) or the `vendor/` folder already present.
4. Git (optional).

Quick checklist
- Apache and MySQL running
- Database imported
- `db.php` configured
- Composer dependencies installed or vendor folder present
- SMTP credentials configured for email (PHPMailer)

Step-by-step Deployment (Local with XAMPP)
1. Place project files
   - Copy or clone repo into XAMPP's htdocs, e.g. `C:\xampp\htdocs\capstone`.
   - If cloning:
     git clone <repository-url> capstone
2. Start services
   - Open XAMPP Control Panel and start **Apache** and **MySQL**.
3. Create the database
   - Open phpMyAdmin (http://localhost/phpmyadmin) and create a database named `capstone` (or any name you prefer).
   - Import the schema: use the SQL file [capstone_schema_rjpines310.sql](capstone_schema_rjpines310.sql) via Import tab.
   - OR from CLI:
     mysql -u root -p capstone < capstone_schema_rjpines310.sql
4. Configure DB connection
   - Edit [db.php](db.php) and set your DB host, username, password, and database name.
   - Example fields to edit: `$servername`, `$username`, `$password`, `$dbname`.
   - Ensure the file permissions are secure.
5. Install Composer dependencies
   - If composer.json exists, run:
     composer install
   - If the host cannot run Composer, upload the project's `vendor/` directory from a machine that ran `composer install`.
   - If you get platform errors, see [vendor/composer/platform_check.php](vendor/composer/platform_check.php) for diagnostics.
6. Configure email (PHPMailer)
   - Update SMTP settings where PHPMailer is used (look for usage in the project).
   - Reference PHPMailer implementation: [vendor/phpmailer/phpmailer/src/PHPMailer.php](vendor/phpmailer/phpmailer/src/PHPMailer.php).
   - Recommended: store SMTP credentials in environment variables and not in code.
7. Set file & folder permissions (Linux)
   - Directories that need web write access (uploads, tmp): chown to web user and chmod appropriately:
     sudo chown -R www-data:www-data /path/to/capstone
     find /path/to/capstone -type d -exec chmod 755 {} \;
     find /path/to/capstone -type f -exec chmod 644 {} \;
8. Configure Apache virtual host (optional)
   - Create a site config to serve from `/var/www/capstone` or point DocumentRoot to `C:\xampp\htdocs\capstone`.
   - Restart Apache after changes.
9. Update application settings (optional)
   - Site base URL, mail settings, and environment toggles may be in config files or inlined — search the project for configuration references (e.g., `db.php`, mail usage).
10. Test the site
    - Open: http://localhost/capstone/login.php ([login.php](login.php))
    - Test user login, DB-driven pages like dashboard. Inspect AJAX endpoints such as [fetch_dashboard_data.php](fetch_dashboard_data.php) and [fetch_resume_summary.php](fetch_resume_summary.php).
11. Troubleshooting
    - Blank page or 500: check Apache/PHP error logs.
    - Database connection errors: verify [db.php](db.php) credentials and DB existence.
    - Composer platform checks: see [vendor/composer/platform_check.php](vendor/composer/platform_check.php) output.
    - Email issues: enable SMTP debug in PHPMailer and check credentials/config.
12. Security & production notes
    - Use HTTPS in production (obtain an SSL certificate).
    - Move sensitive credentials out of files into environment variables.
    - Restrict file permissions and remove dev-only files or scripts.
    - Do not expose `.git` or backups publicly.
    - Consider setting up cron jobs for periodic tasks (backups, cleanup).
13. Deployment to remote host
    - Upload files via SFTP/FTP to public folder or use Git + CI.
    - Create remote DB and import schema.
    - Run `composer install` on server (if available) or upload `vendor/`.
    - Configure environment variables and SMTP on remote host.
14. Useful references in this workspace
    - Messaging improvements guide: [MESSAGING_IMPROVEMENTS_GUIDE.md](MESSAGING_IMPROVEMENTS_GUIDE.md)
    - Project TODOs: [TODO.md](TODO.md)

Common Commands
- Composer install:
  composer install
- Import DB via CLI:
  mysql -u root -p capstone < capstone_schema_rjpines310.sql
- Start XAMPP: open XAMPP Control Panel

If you followed the steps above and encounter an issue, check Apache/PHP logs and paste the error message into the issue tracker or open a support thread.

Minimal sanity-check pages to load after deployment:
- [login.php](login.php)
- Any dashboard page (login then verify AJAX endpoints like [fetch_dashboard_data.php](fetch_dashboard_data.php))

Notes
- This guide assumes the database schema file is named [capstone_schema_rjpines310.sql](capstone_schema_rjpines310.sql) as referenced in project docs.
- For email functionality, review [vendor/phpmailer/phpmailer/src/PHPMailer.php](vendor/phpmailer/phpmailer/src/PHPMailer.php).

End of deployment guide.
