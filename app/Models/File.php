<?php
// app/Models/File.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size'
    ];

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cases()
    {
        return $this->belongsToMany(CaseFile::class, 'case_file_file');
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'file_task');
    }

    public function minutes()
    {
        return $this->belongsToMany(Minute::class, 'file_minute');
    }

    // Helper methods
    public function getUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    public function getFullPathAttribute()
    {
        return storage_path('app/public/' . $this->file_path);
    }
}