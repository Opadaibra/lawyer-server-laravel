<?php
// database/migrations/2024_01_01_000006_create_case_file_file_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_file_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_file_id')
                ->constrained('case_files')
                ->cascadeOnDelete();
            $table->foreignId('file_id')
                ->constrained('files')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['case_file_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_file_file');
    }
};