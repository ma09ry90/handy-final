<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'password', 
        'phone_number', 'role_id', 'account_status', 'birthdate', 
        'last_login_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function artisanProfile() { return $this->hasOne(ArtisanProfile::class); }
    public function deliveryProfile() { return $this->hasOne(DeliveryProfile::class); }
    
    public function isAdmin() { return $this->role_id === 4; }
    public function isArtisan() { return $this->role_id === 2; }
    public function isDelivery() { return $this->role_id === 3; }
    public function isBuyer() { return $this->role_id === 1; }
}