<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseSession extends Model
{
    protected $fillable = [
        'case_file_id',
        'date',
        'decisions',
        'notes'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class);
    }
}
