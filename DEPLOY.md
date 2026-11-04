# Deployen op ubuntu-vm (Tailscale)

API-adres: https://ubuntu-vm.tailbc85b1.ts.net:8443/api
Frontend:  https://ubuntu-vm.tailbc85b1.ts.net  (staat al online)

Het enige dat je zelf invult: `DB_USERNAME` en `DB_PASSWORD`
(in `.env` én in `deploy/mysql-setup.sql` als JOUW_GEBRUIKER / JOUW_WACHTWOORD).

## Eenmalig

```bash
# 1. Software
sudo apt update
sudo apt install -y git unzip mysql-server composer nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip

# 2. Code ophalen
cd /var/www
sudo git clone <jullie-repo-url> bugbusters-backend
sudo chown -R $USER:www-data bugbusters-backend
cd bugbusters-backend

# 3. Database (eerst JOUW_GEBRUIKER en JOUW_WACHTWOORD aanpassen in het bestand)
nano deploy/mysql-setup.sql
sudo mysql < deploy/mysql-setup.sql

# 4. Configuratie
cp .env.example .env
nano .env            # alleen DB_USERNAME en DB_PASSWORD invullen

# 5. Deployen
./deploy.sh

# 6. Schrijfrechten
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 7. Webserver (nginx, luistert lokaal op 127.0.0.1:8081)
sudo cp deploy/nginx.conf /etc/nginx/sites-available/campusconnect
sudo ln -s /etc/nginx/sites-available/campusconnect /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# 8. HTTPS via Tailscale (poort 443 blijft voor je frontend)
sudo tailscale serve --bg --https=8443 http://127.0.0.1:8081
tailscale serve status
```

## Elke update

```bash
cd /var/www/bugbusters-backend && ./deploy.sh
```

## Testen (vanaf een apparaat op je Tailscale-netwerk)

```bash
curl -i https://ubuntu-vm.tailbc85b1.ts.net:8443/up
curl -i -X POST https://ubuntu-vm.tailbc85b1.ts.net:8443/api/login -H "Accept: application/json"
```

Eerste geeft 200, tweede een JSON-fout (422), geen HTML.

## Frontend koppelen

Gebruik `deploy/frontend-api.js` (API-URL staat er al in).

## Problemen

- `could not find driver`: `sudo apt install php8.3-mysql`
- `Access denied`: gebruikersnaam/wachtwoord in `.env` komt niet overeen met `mysql-setup.sql`; daarna `php artisan config:cache`
- CORS-fout in de browser: controleer `CORS_ALLOWED_ORIGINS` in `.env`; daarna `php artisan config:cache`
- 502 Bad Gateway: andere PHP-versie? Pas `php8.3-fpm.sock` aan in `nginx.conf`
- Rechtenfout op logs: stap 6 opnieuw uitvoeren
