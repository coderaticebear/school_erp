# AWS demo deployment: runbook

Plan agreed 2026-10-09. This is a **demo/showcase** deployment on one EC2 instance, paid from the new account's free tier and $100 credits. Do not put real student data on it until the security audit (SEC items) is finished.

## Decisions

| Topic | Decision |
|---|---|
| Purpose | Demo / showcase, low traffic |
| Runtime | Docker Compose, production images (`docker/prod/Dockerfile`, `compose.prod.yaml`): php-fpm app, Caddy web, Postgres, scheduler |
| Database | PostgreSQL 18 container on the same instance, named volume, nightly `pg_dump` to S3 |
| HTTPS | Free DuckDNS subdomain, Let's Encrypt certificate handled by Caddy |
| Environment | `APP_ENV=production`, **no demo seeding** (SEC-07). First admin comes from `php artisan create-admin` |
| Deploy flow | Manual: SSH in, run `scripts/deploy.sh` |
| Email | None. `MAIL_MAILER=array` discards mail, so password resets don't arrive and an admin sets passwords |
| Instance | `t3.small` (2 GB), Ubuntu 24.04 LTS, nearest region. Check free-plan eligibility in the console; if not eligible use `t3.micro` plus 2 GB swap |

Still to fill in when the server exists: region, instance ID, Elastic IP, DuckDNS name, S3 bucket name.

## Architecture

```
Internet -> :80/:443 web (Caddy, TLS)  -> static files from public/
                                       -> FastCGI -> app (php-fpm)  -> pgsql (Postgres 18)
                                          scheduler (schedule:work: sessions:prune hourly)
```

- Only ports 22 (your IP only), 80 and 443 are open. Postgres, php-fpm, pgAdmin and Mailpit are not published.
- **Two env files.** `.env` holds app settings and the row-access DB password (read by app, scheduler, web). `.env.deploy` holds the Postgres superuser and schema-owner passwords (read only by `pgsql` and the one-off `tools` service). Both are server-only and git-ignored; examples are `.env.production.example` and `.env.deploy.example`.
- **No trusted-proxy change needed.** Caddy talks FastCGI straight to php-fpm, so `REMOTE_ADDR` is the visitor's address and HTTPS is detected. The SEC-19 note about `trustProxies` applies only if an HTTP reverse proxy or load balancer is put in front later.
- `route:cache` is not used: `routes/web.php` has closure routes. Containers run `config:cache`, `event:cache`, `view:cache` on start.

## One-time AWS setup

1. **Account safety.** Turn on MFA for the root user, create an IAM admin user for daily work, and create a **$10 budget alert** (Billing > Budgets) before launching anything.
2. **Region + key pair.** Pick the nearest region. Create an EC2 key pair (store the `.pem` in `~/.ssh`, `chmod 400`).
3. **Security group.** Inbound: SSH 22 from *my IP only*, HTTP 80 and HTTPS 443 from anywhere. Nothing else.
4. **S3 backup bucket.** Block all public access, default encryption on, lifecycle rule to expire objects after ~30 days.
5. **IAM role for the instance.** Policy allowing only `s3:PutObject` (and `s3:ListBucket` if wanted) on that bucket. Attach as the instance profile.
6. **Launch the instance.** Ubuntu 24.04 LTS, `t3.small`, 20-30 GB gp3 **encrypted**, IMDSv2 required, the security group and instance profile above.
7. **Elastic IP.** Allocate and associate it, so the address survives restarts. It is free while attached to a running instance and billed if left unattached.
8. **DuckDNS.** Create a subdomain at duckdns.org and point it at the Elastic IP.

## One-time server setup (SSH in as `ubuntu`)

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install unattended-upgrades ca-certificates curl git unzip ufw

# Docker Engine + compose plugin (official convenience script)
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker ubuntu        # log out and back in afterwards

# Host firewall (the security group already filters; this is a second layer)
sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw --force enable

# AWS CLI v2 (for backups)
curl -fsSL "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o awscliv2.zip
unzip -q awscliv2.zip && sudo ./aws/install
```

If the repo is private, create a read-only **deploy key** (`ssh-keygen -t ed25519`), add the public key in GitHub (repo Settings > Deploy keys), then clone over SSH.

```bash
git clone git@github.com:coderaticebear/school_erp.git && cd school_erp
cp .env.production.example .env
cp .env.deploy.example .env.deploy && chmod 600 .env .env.deploy
```

Edit both files:
- `.env`: set `APP_URL`, `SITE_ADDRESS` (the DuckDNS host name), `DB_PASSWORD`, `SCHOOL_NAME`. Leave `APP_KEY` empty for now.
- `.env.deploy`: set `POSTGRES_PASSWORD` and `DB_ADMIN_PASSWORD` to the same strong value, `DB_MIGRATION_PASSWORD`, and `BACKUP_BUCKET`.

Use long random values, for example `openssl rand -base64 24`.

## First deploy

```bash
C="docker compose -f compose.prod.yaml"
$C build
$C up -d pgsql

# Generate the app key once and put it in .env as APP_KEY=base64:...
$C run --rm tools php artisan key:generate --show

# Create the school_owner and school_app roles and grant access (safe to re-run)
$C run --rm tools php artisan db:provision-roles school_erp --force

# Schema, then the first admin (password is printed once; save it)
$C run --rm tools php artisan migrate --database=pgsql_migrations --force
$C run --rm tools php artisan create-admin you@example.com

$C up -d
$C ps
```

Caddy requests the certificate on first start, so the DuckDNS name must already resolve to the Elastic IP and ports 80/443 must be open.

Add the backup cron: `crontab -e`, then `15 2 * * * /home/ubuntu/school_erp/scripts/backup.sh >> /home/ubuntu/backup.log 2>&1`. Run the script by hand once and confirm the object appears in S3.

## Updating the demo

```bash
cd ~/school_erp && ./scripts/deploy.sh
```

It pulls, rebuilds, runs migrations as the schema owner, and restarts the containers.

## Restoring a backup (test this once)

```bash
aws s3 cp s3://BUCKET/school_erp-STAMP.sql.gz - | gunzip \
  | docker compose -f compose.prod.yaml exec -T pgsql psql -U sail -d school_erp_restore_test
```

Create the scratch database first (`createdb`), and never restore over the live one without stopping the app.

## Verification checklist

- [ ] `https://<name>.duckdns.org` loads with a valid certificate; `http://` redirects to `https://`.
- [ ] `curl -sI https://<name>.duckdns.org/login` shows `Strict-Transport-Security`, `Content-Security-Policy` and `X-Frame-Options`.
- [ ] Admin login works; create a teacher, student and parent in the UI and check each portal opens.
- [ ] Port scan from outside shows only 80/443 (22 only from your IP); Postgres is not reachable.
- [ ] `docker compose -f compose.prod.yaml ps` shows all services up; `logs scheduler` shows `sessions:prune` running hourly.
- [ ] Forgot-password page shows the generic reply and doesn't error with `MAIL_MAILER=array`.
- [ ] Backup object in S3 and a successful test restore.

## Costs and cleanup

Expected well under $100 over 6 months (instance, 30 GB EBS, one Elastic IP while attached, a few cents of S3). When the demo is finished, **terminate the instance, release the Elastic IP, delete leftover EBS volumes/snapshots and the S3 bucket**; unattached IPs and volumes keep billing.

## Gotchas specific to this repo

- DB roles: the app only ever connects as `school_app`. Migrations need `--database=pgsql_migrations --force`; `db:provision-roles` needs the superuser from `.env.deploy`. That is why one-off commands run in the `tools` service, never in `app`.
- Demo seeders throw in production (SEC-07), so the demo starts empty. A populated demo would need a separate non-production environment and shows known passwords (`password`), so don't expose that publicly.
- Changing `APP_KEY` after launch invalidates encrypted sessions and anything encrypted with it.
- Logs go to `storage/logs` (volume `storage`) with personal data masked (SEC-04). Keep any new log channel's `tap`.
- Self-hosted fonts/DataTables live in `public/vendor` and are copied into the web image; never add CDN links (SEC-05).
