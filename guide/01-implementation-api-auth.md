# Authentication API Endpoint Implementation Guide

## Step 1 — Install API & Scaffolding

```bash
php artisan install:api
```

This creates:
- `config/sanctum.php`
- `database/migrations/2026_07_03_172409_create_personal_access_tokens_table.php`
- `routes/api.php`
- `bootstrap/app.php` (modify)

## Step 2 — Modify `personal_access_tokens` & `users` table.

**File:** `database/migrations/0000_00_00_000000_create_personal_access_tokens_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable'); // uuidMorphs because User uses UUID primary keys
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};

```

**File:** `database/migrations/0000_00_00_000000_create_users_table.php`


```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID instead of auto-increment
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('pin', 60)->nullable(); // hashed 4-digit offline unlock PIN
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index(); // UUID foreign key matching users.id
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

```

Key changes from default:
- `$table->uuidMorphs('tokenable');` instead of `$table->morphs('tokenable');`
- `$table->uuid('id')->primary();` instead of `$table->id();`
- `$table->string('pin', 60)->nullable();` // hashed 4-digit offline unlock PIN
- `$table->foreignUuid('user_id')->nullable()->index(); instead of $table->foreignId('user_id')->nullable()->index();`

---

## Step 3 — Update User Model (UUIDs + Sanctum + PIN)

**File:** `app/Models/User.php`

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // enables UUID primary key generation
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // enables API token management

#[Fillable(['name', 'email', 'password', 'pin'])] //pin field added
#[Hidden(['password', 'remember_token', 'pin'])] //pin field added
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasApiTokens; // HasUuids for UUID PK, HasApiTokens for Sanctum
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public $incrementing = false; // UUIDs are not auto-incrementing
    protected $keyType = 'string'; // UUID primary keys are strings
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

```

---

## Step 4 — Create API Routes

**File:** `routes/api.php` (new file)

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/validate-token', [AuthController::class, 'validateToken']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/verify-pin', [AuthController::class, 'verifyPin']);
    });

});
```

---

## Step 5 — Create AuthController

**File:** `app/Http/Controllers/Api/V1/AuthController.php` (new file)

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'pin'      => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'pin'      => Hash::make($request->pin),
        ]);

        $token = $user->createToken(
            'auth-token',
            ['*'],
            now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken(
            'auth-token',
            ['*'],
            now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    public function validateToken(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->forceFill([
                'expires_at' => now()->addDays(30),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid. Expiration extended.',
            'user'    => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully. Token revoked.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user'    => $request->user(),
        ]);
    }

    public function verifyPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user->pin || !Hash::check($request->pin, $user->pin)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided PIN is incorrect.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'PIN verified successfully.',
        ]);
    }
}
```

---

## Step 6 — Configure `.env` File

**File:** `.env` (at project root)

Laravel ships with a `.env.example` file. Copy it to `.env` if one doesn't already exist, then update the following values:

```bash
cp .env.example .env    # skip if .env already exists
```

Use a **local-development** / **production** block pattern so you can toggle environments by commenting/uncommenting:

```env
APP_NAME=API
APP_KEY=base64:uSnka2Ocrh9GwpQV/c28df5YvK+5X9FiglmK732rppU=

# ─────────────────────────────────────────────
# LOCAL DEVELOPMENT
# ─────────────────────────────────────────────
APP_ENV=local
APP_URL=http://192.168.0.151:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api-sirikotia
DB_USERNAME=root
DB_PASSWORD=
APP_DEBUG=true

# ─────────────────────────────────────────────
# PRODUCTION / LIVE SERVER
# ─────────────────────────────────────────────
# APP_ENV=production
# APP_URL=https://api.yourdomain.com/
# DB_CONNECTION=mysql
# DB_HOST=your-production-host
# DB_PORT=3306
# DB_DATABASE=your_production_db
# DB_USERNAME=your_production_user
# DB_PASSWORD=your_production_password
# APP_DEBUG=false

# ─────────────────────────────────────────────
# Sanctum Settings
# ─────────────────────────────────────────────
SANCTUM_STATEFUL_DOMAINS=${APP_URL}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
# APP_MAINTENANCE_STORE=database

# PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
# CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"
```

> **`SANCTUM_STATEFUL_DOMAINS=${APP_URL}`** reuses the `APP_URL` value — no need to hardcode the domain twice. This is only needed for Sanctum's SPA cookie auth; the Flutter app uses Bearer tokens and is unaffected.
>
> **`SESSION_DRIVER=database`** stores sessions in the database. For API-only backends you can use `file` instead.
> After any `.env` change, clear the config cache:
> ```bash
> php artisan config:clear
> ```

---

## Step 7 — Run Migrations

```bash
php artisan migrate
```

> If you already ran migrations before adding the `pin` column, refresh instead:
> ```bash
> php artisan migrate:fresh
> ```

---

## Step 8 — Start Server

```bash
php artisan serve --host=192.168.0.151 --port=8000
```
> Change according to your Local IP address

Open [192.168.0.151:8000](http://192.168.0.151:8000)

### Or use the automate batch script:

**File:** `artisan-serve.bat` (root folder)

```batch
@echo off
title Laravel Server - 192.168.0.151
echo Starting Laravel application on 192.168.0.151:8000...
cd /d "%~dp0"

start "" "http://192.168.0.151:8000"

php artisan serve --host=192.168.0.151 --port=8000
pause
```
---
## Step 9 — Check Route List
```bash
php artisan route:list
```
---

## API Endpoints & Usage-

### POST http://192.168.0.151:8000/api/v1/register [Register a new user + 4-digit PIN]

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "pin": "1234"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Registration successful.",
  "token": "1|abc123def456...",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2026-07-04T00:00:00.000000Z",
    "updated_at": "2026-07-04T00:00:00.000000Z"
  }
}
```

**Error (422):**
```json
{
  "success": false,
  "errors": {
    "pin": ["The pin field must be 4 digits."]
  }
}
```

---

### POST http://192.168.0.151:8000/api/v1/login [Login and receive a token]

**Request:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "success": true,
  "token": "2|xyz789abc012...",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

**Error (401):**
```json
{
  "success": false,
  "message": "The provided credentials are incorrect."
}
```

---

### GET http://192.168.0.151:8000/api/v1/validate-token [Validate/extend token expiry]

**Headers:** `Authorization: Bearer 2|xyz789abc012...`

**Response (200):**
```json
{
  "success": true,
  "message": "Token is valid. Expiration extended.",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

---

### POST http://192.168.0.151:8000/api/v1/logout [Revoke current token]

**Headers:** `Authorization: Bearer 2|xyz789abc012...`

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully. Token revoked."
}
```

---

### GET http://192.168.0.151:8000/api/v1/user [Get authenticated user profile]

**Headers:** `Authorization: Bearer 2|xyz789abc012...`

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

---

### POST http://192.168.0.151:8000/api/v1/verify-pin [Verify 4-digit offline unlock PIN]

**Headers:** `Authorization: Bearer 2|xyz789abc012...`

**Request:**
```json
{
  "pin": "1234"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "PIN verified successfully."
}
```

**Error (401):**
```json
{
  "success": false,
  "message": "The provided PIN is incorrect."
}
```


