<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseSession extends Model
{
    protected $fillable = [
        'case_file_id',
        'date',
        'decisions',
        'notes',
        'archived_at'
    ];

    protected $casts = [
        'date' => 'datetime',
        'archived_at' => 'datetime'
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class);
    }
}
