<?php
// database/migrations/2024_01_01_000007_create_file_task_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();
            $table->foreignId('file_id')
                ->constrained('files')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['task_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_task');
    }
};