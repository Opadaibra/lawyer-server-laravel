<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseNote extends Model
{
    protected $fillable = [
        'case_file_id',
        'date',
        'content'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class);
    }
}
