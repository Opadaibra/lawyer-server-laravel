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
        Schema::table('minutes', function (Blueprint $table) {
            $table->date('date')->nullable()->after('case_file_id');
            $table->string('number')->nullable()->after('date');
            $table->string('court_department')->nullable()->after('number');
            $table->string('client_status')->nullable()->after('court_department');
            $table->string('opponent')->nullable()->after('client_status');
            $table->string('opponent_status')->nullable()->after('opponent');
            $table->text('last_procedure')->nullable()->after('opponent_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('minutes', function (Blueprint $table) {
            $table->dropColumn([
                'date',
                'number',
                'court_department',
                'client_status',
                'opponent',
                'opponent_status',
                'last_procedure'
            ]);
        });
    }
};
