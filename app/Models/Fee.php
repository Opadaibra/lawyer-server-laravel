<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $fillable = [
        'case_file_id',
        'date',
        'value',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'value' => 'decimal:2'
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class);
    }
}
