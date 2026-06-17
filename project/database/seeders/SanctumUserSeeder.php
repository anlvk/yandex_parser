<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SanctumUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        // Удаляем старого пользователя, если он был, чтобы не было дубликатов
        User::where('email', 'someemail@gmail.com')->delete();

        User::create([
            'name' => 'Seeded Sanctum User',
            'email' => 'someemail@gmail.com',
            'password' => Hash::make('somepassword'), // Обязательно Hash::make для проверки Laravel
        ]);
    }
}
