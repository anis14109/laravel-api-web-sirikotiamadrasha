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
