<?php
// app/Models/Task.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory ;

    protected $fillable = [
        'user_id',
        'case_file_id',
        'title',
        'description',
        'due_date',
        'status',
        'task_type',
        'next_session_date',
        'notes',
        'archived_at'
    ];

    protected $casts = [
        'due_date' => 'date',
        'next_session_date' => 'date',
        'archived_at' => 'datetime'
    ];

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function case()
    {
        return $this->belongsTo(CaseFile::class, 'case_file_id');
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'file_task');
    }

    
}