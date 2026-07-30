### Step 1: Create the Project

```bash
composer create-project laravel/laravel sirikotia-laravel-api
```

### Step 2: Navigate into the Project Folder
```bash
cd sirikotia-laravel-api
```

### step 4: Copy environment file & Change local database info
```bash
cp .env.example .env

### Change DB Info
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sirikotia-laravel-api
DB_USERNAME=root
DB_PASSWORD=
```

### step 5: Generate the app key (creates `APP_KEY` if missing):

```bash
php artisan key:generate
```

### step 6: Run Migrations (creates database):

```bash
php artisan migrate
```

### step 6: Run Migrations (creates database):

```bash
php artisan migrate
```

### Step 7 — Start Server

```bash
php artisan serve
```
`or` change ip as your need to access locally from any device.
```
php artisan serve --host=192.168.0.151 --port=8000
```

