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
        Schema::table('organizations', function (Blueprint $table) {
            //// Средний рейтинг (число с плавающей точкой, например, 4.8)
            $table->decimal('rating', 3, 1)->nullable()->after('address');
            
            // Количество оценок (целое число)
            $table->integer('rating_count')->nullable()->after('rating');
            
            // Количество текстовых отзывов отдельно (целое число)
            $table->integer('review_count')->nullable()->after('rating_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            //
            $table->dropColumn(['rating', 'rating_count', 'review_count']);
        });
    }
};
