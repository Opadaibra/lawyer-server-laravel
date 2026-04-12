<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'is_read',
        'task_id',
        'minute_id',
        'case_file_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function minute()
    {
        return $this->belongsTo(Minute::class);
    }

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class);
    }
}
