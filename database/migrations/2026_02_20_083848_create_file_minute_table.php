<?php
// database/migrations/2024_01_01_000008_create_file_minute_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_minute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minute_id')
                ->constrained('minutes')
                ->cascadeOnDelete();
            $table->foreignId('file_id')
                ->constrained('files')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['minute_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_minute');
    }
};