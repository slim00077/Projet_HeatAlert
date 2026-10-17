# Projet HeatAlert

HeatAlert est une application web qui aide les habitants à anticiper les fortes chaleurs et trouver des informations utiles. 

## Contribution : Membre 1 (Alertes Chaleur, Quartiers, IA, Authentification)
Réalisé par **Jasser Nahali**

### Fonctionnalités avancées
- **Météo réelle (Open-Meteo)** : prévisions par quartier, alertes automatiques (35 °C = moyenne, 40 °C = élevée), commande `php artisan weather:sync` planifiée toutes les 3 heures.
- **Carte interactive (OpenStreetMap / Leaflet, Mapbox en option)** : zones colorées par niveau de risque, température, population, résidences du quartier, alertes actives, signalement d'un point chaud par clic sur la carte.
- **Bandeau de danger** au-dessus de la barre de navigation lorsqu'une alerte élevée est active.
- **Notifications** aux habitants du quartier (mail + base de données, via la file d'attente), API JSON `GET /api/v1/alerts/active` et `GET /api/v1/neighborhoods`.
- **Rôles** : résident, gestionnaire de résidences (crée les résidences, validées par l'admin, puis ajoute les habitants) et administrateur.

### Fonctionnalités IA (Claude)
- **Conseiller chaleur** : bref de sécurité par quartier et question libre depuis la carte.
- **Rédaction assistée** du titre et de la description d'une alerte dans le back-office.
- Clé `ANTHROPIC_API_KEY` dans `.env` ; sans clé, un texte de secours basé sur des règles est utilisé et indiqué comme tel.

### Gestion des utilisateurs (Laravel Fortify)
Inscription, connexion, déconnexion, vérification d'e-mail, réinitialisation du mot de passe (jeton expirant), rôles contrôlés côté serveur (middleware + policies), authentification à deux facteurs (QR code TOTP + codes de récupération), profil (informations, mot de passe, photo, suppression du compte).

### DevOps
- `Dockerfile` multi-étapes (assets Vite, dépendances, PHP-FPM, nginx) et `docker-compose.yml` (application, nginx, MySQL, worker de file d'attente, planificateur).
- GitHub Actions : `ci.yml` (Pint, Pest, build de l'image) et `deploy.yml` (publication de l'image sur GHCR depuis `main`).

```
cp .env.docker.example .env.docker      # puis renseigner APP_KEY (php artisan key:generate --show)
docker compose up --build
docker compose exec app php artisan migrate:fresh --seed
# application : http://localhost:8080
```

### Lancer en local
```
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
npm run build            # ou npm run dev
php artisan serve        # + php artisan queue:work et php artisan schedule:work
```
Comptes de démonstration (mot de passe `password`) : `admin@heatalert.test`, `sami.manager@heatalert.test`, `leila.manager@heatalert.test`, `amine@heatalert.test`.

Tests : `php artisan test` (Pest).

### Workflow Git
Une branche par membre (`jasser`, `aziz`, `NizarChaieb`) fusionnée dans `develop` par Pull Request (modèle fourni dans `.github/pull_request_template.md`), commits au format conventionnel (`feat:`, `fix:`, `ci:`...), CI obligatoire avant fusion.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
