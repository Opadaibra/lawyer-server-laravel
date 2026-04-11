<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('id')->constrained('offices')->nullOnDelete();
            $table->enum('role', ['MANAGER', 'EDITOR', 'VIEWER', 'LAWYER', 'CLIENT'])->default('LAWYER')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->enum('role', ['ADMIN', 'LAWYER'])->default('LAWYER')->change();
        });
    }
};
