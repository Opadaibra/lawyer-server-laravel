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
        Schema::table('case_files', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('case_type');
            $table->string('court_department')->nullable()->after('court');
            $table->string('client_status')->nullable()->after('court_department');
            $table->string('opponent')->nullable()->after('client_status');
            $table->string('opponent_status')->nullable()->after('opponent');
            $table->decimal('total_fees_payments', 15, 2)->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_files', function (Blueprint $table) {
            $table->dropColumn([
                'subject',
                'court_department',
                'client_status',
                'opponent',
                'opponent_status',
                'total_fees_payments'
            ]);
        });
    }
};
