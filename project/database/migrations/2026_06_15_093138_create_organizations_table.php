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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            // Привязываем организацию к пользователю, который её добавил
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Данные Яндекса
            $table->string('yandex_id')->unique(); // Уникальный ID из ссылки
            $table->text('yandex_url');            // Сама ссылка
            
            // Поля для данных, которые мы будем подтягивать (сделаем их nullable, так как они заполняются чуть позже)
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('working_hours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
