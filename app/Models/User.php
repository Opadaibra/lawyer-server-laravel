<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'office_id',
        'profile_picture',
        'otp',
        'otp_expires_at',
        'last_otp_requested_at',
        'otp_request_count'
    ];

    protected $appends = ['profile_picture_url'];

    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture) {
            return asset('storage/' . $this->profile_picture);
        }
        return null;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // هذا يساعد في Laravel 10+
        'last_otp_requested_at' => 'datetime',
    ];

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // العلاقات
    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function clientProfile()
    {
        return $this->hasOne(Client::class, 'client_user_id');
    }

    public function cases()
    {
        return $this->hasMany(CaseFile::class);
    }

    public function getOfficeUsersIdsAttribute()
    {
        if ($this->office_id) {
            return self::where('office_id', $this->office_id)->pluck('id')->toArray();
        }
        return [$this->id];
    }
}