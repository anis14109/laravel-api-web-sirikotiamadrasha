# Installation Guide

**Clone Git Url**
```bash
git clone https://github.com/anis14109/laravel-api-web-sirikotiamadrasha.git
cd laravel-api-web-sirikotiamadrasha
```

**Install Composer** (Genereate Vendor folder):

```bash
composer install
```

**Copy environment file**
```bash
cp .env.example .env
```
Change local database info

**Generate the app key** (creates `APP_KEY` if missing):

```bash
php artisan key:generate
```

**Run Migrations** (creates database):

```bash
php artisan migrate
```