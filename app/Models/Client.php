<?php
// app/Models/Client.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_user_id',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'power_of_attorney_number'
    ];

    protected $dates = ['deleted_at'];

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clientUser()
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function cases()
    {
        return $this->hasMany(CaseFile::class);
    }
}