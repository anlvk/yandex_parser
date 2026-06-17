<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class YandexMapsParserService
{
    /**
     * Запускает комплексный процесс парсинга мета-данных и отзывов
     */
    public function parse(Organization $organization, string $yandexId): bool
    {
        // 1. Собираем средний рейтинг, число оценок и число отзывов
        $this->parseMeta($organization, $yandexId);

        // 2. Выкачиваем до 600 отзывов глубоким скроллом (через API пагинацию)
        $this->parseReviews($organization, $yandexId);

        return true;
    }

    /**
     * Сбор мета-данных (Рейтинг, Количества)
     */
    private function parseMeta(Organization $organization, string $yandexId): void
    {
        $url = "https://yandex.ru{$yandexId}";

        try {
            $response = Http::withHeaders($this->getHeaders())->timeout(10)->get($url);

            if ($response->failed()) {
                throw new Exception("Яндекс отдал ошибку " . $response->status());
            }

            $json = $response->json();
            
            if (empty($json['data'])) {
                throw new Exception("Получен пустой ответ от структуры разметки Яндекса.");
            }

            $organization->update([
                'name'         => $json['data']['name'] ?? 'Организация #' . $yandexId,
                'address'      => $json['data']['address'] ?? 'Адрес скрыт',
                'rating'       => (float) ($json['data']['rating']['value'] ?? 0),
                'rating_count' => (int) ($json['data']['rating']['count'] ?? 0),
                'review_count' => (int) ($json['data']['reviews']['count'] ?? 0),
            ]);

        } catch (Exception $e) {
            logger()->error("Критический сбой сбора МЕТА для {$yandexId}: " . $e->getMessage());
            throw new Exception("Не удалось собрать общую информацию: " . $e->getMessage());
        }
    }

    /**
     * Глубокий сбор до 600 отзывов порциями по 50 штук
     */
    private function parseReviews(Organization $organization, string $yandexId): void
    {
        $page = 1;
        $maxReviews = 600;
        $totalFetched = 0;
        $pageSize = 50;

        while ($totalFetched < $maxReviews) {
            // Задержка между страницами (Throttling) против систем детекции ботов
            sleep(rand(1, 2));

            $url = "https://yandex.ru{$yandexId}&page={$page}&pageSize={$pageSize}&ranking=by_time";

            try {
                $response = Http::withHeaders($this->getHeaders())->timeout(10)->get($url);

                if ($response->status() === 403 || $response->status() === 429) {
                    throw new Exception("Блокировка Anti-Bot системы (Капча / Частота запросов).");
                }

                if ($response->failed()) break;

                $json = $response->json();
                $reviewsList = $json['data']['reviews'] ?? [];

                if (empty($reviewsList)) break; // Отзывы закончились раньше, чем 600

                foreach ($reviewsList as $reviewItem) {
                    if ($totalFetched >= $maxReviews) break;

                    $authorName = $reviewItem['author']['name'] ?? 'Аноним';
                    $text       = $reviewItem['text'] ?? '';
                    $stars      = (int) ($reviewItem['rating'] ?? 0);
                    $timestamp  = $reviewItem['created_at'] ?? time();

                    // Сохранение/Обновление без дублирования в PostgreSQL
                    $organization->reviews()->updateOrCreate(
                        ['author_name' => $authorName, 'text' => $text],
                        ['stars' => $stars, 'publish_date' => Carbon::createFromTimestamp($timestamp)->format('Y-m-d')]
                    );

                    $totalFetched++;
                }

                $page++;

            } catch (Exception $e) {
                logger()->error("Ошибка сбора отзывов для {$yandexId} на странице {$page}: " . $e->getMessage());
                throw new Exception("Ошибка при глубоком импорте отзывов: " . $e->getMessage());
            }
        }
    }

    private function getHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8',
        ];
    }
}
