<?php
// database/migrations/2024_01_01_000005_create_minutes_table.php (التاريخ حيكون مختلف)

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
        Schema::create('minutes', function (Blueprint $table) {
            $table->id();
            
            // المفاتيح الخارجية
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
                  
            $table->foreignId('case_file_id')
                  ->constrained('case_files')
                  ->cascadeOnDelete();
            
            // الحقول الأساسية
            $table->string('title');
            $table->longText('content');
            
            // soft delete / archive
            $table->timestamp('archived_at')->nullable();
            
            // timestamps
            $table->timestamps();
            
            // إضافة indexes للبحث السريع
            $table->index(['user_id', 'case_file_id']);
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minutes');
    }
};