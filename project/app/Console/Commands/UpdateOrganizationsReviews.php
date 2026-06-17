<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use Illuminate\Support\Facades\Http;

class UpdateOrganizationsReviews extends Command
{
    /**
     * Сигнатура (команда, которую мы будем вводить в терминале)
     */
    protected $signature = 'app:update-reviews';

    /**
     * Описание команды для списка artisan
     */
    protected $description = 'Фоновое обновление отзывов (до 600 штук) для всех организаций из Яндекс.Карт';

    public function handle()
    {
        $this->info('Старт обновления отзывов...');

        // 1. Получаем из базы все привязанные организации
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->warn('В базе данных пока нет организаций для обновления.');
            return Command::SUCCESS;
        }

        foreach ($organizations as $organization) {
            $this->info("Обработка организации ID: {$organization->yandex_id} ({$organization->name})");

            try {
                // В реальном проекте здесь делается запрос к вашему провайдеру данных/API.
                // Передаем id Яндекса и просим лимит до 600 штук.
                // Пример: $response = Http::get("https://parser.com{$organization->yandex_id}&limit=600");
                
                // Имитируем получение массива глубоких данных (до 600 штук)
                $fetchedReviews = $this->getMockBulkReviews(); 

                $this->info("Получено " . count($fetchedReviews) . " отзывов от поставщика.");

                $insertedCount = 0;

                // 2. Запускаем цикл сохранения
                foreach ($fetchedReviews as $reviewData) {
                    // Используем метод Eloquent, чтобы не плодить дубликаты при ежедневном обновлении
                    $organization->reviews()->updateOrCreate(
                        [
                            // Уникальный ключ проверки (комбинация автора и текста отзыва)
                            'author_name' => $reviewData['author_name'],
                            'text'        => $reviewData['text'],
                        ],
                        [
                            // Обновляемые параметры
                            'stars'        => $reviewData['stars'],
                            'publish_date' => $reviewData['publish_date'],
                        ]
                    );
                    $insertedCount++;
                }

                $this->info("Успешно синхронизировано {$insertedCount} отзывов для этой компании.");

            } catch (\Exception $e) {
                $this->error("Ошибка при обработке компании {$organization->yandex_id}: " . $e->getMessage());
            }
        }

        $this->info('Синхронизация всех отзывов успешно завершена!');
        return Command::SUCCESS;
    }

    /**
     * Временный генератор тестового массива (до 600 отзывов)
     */
    private function getMockBulkReviews(): array
    {
        $reviews = [];
        // Генерируем тестовый массив, чтобы проверить, как PostgreSQL справляется с объемом
        for ($i = 1; $i <= 150; $i++) {
            $reviews[] = [
                'author_name' => "Пользователь Яндекс.Карт #{$i}",
                'stars' => rand(3, 5),
                'text' => "Тестовый текст развернутого отзыва под номером {$i}. Все очень понравилось, придем еще раз обязательно!",
                'publish_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
            ];
        }
        return $reviews;
    }
}
