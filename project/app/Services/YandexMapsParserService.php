<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Exception;

class YandexMapsParserService
{
    /**
     * Публичный метод для запуска первичного сбора мета-данных и 1 страницы отзывов
     */
    public function parseInitial(Organization $organization, string $yandexId): void
    {
        // Сначала собираем общую информацию из HTML
        $this->parseMeta($organization, $yandexId);
        
        // Затем скачиваем первую страницу отзывов (50 шт) через API
        $this->parseReviewPage($organization, $yandexId, 1);
    }

    /**
     * Сбор мета-данных организации напрямую из HTML страницы (Schema.org)
     */
    public function parseMeta(Organization $organization, string $yandexId): void
    {
        $url = $organization->yandex_url;

        try {
            $response = Http::withHeaders($this->getHeaders())->timeout(12)->get($url);

            if ($response->failed()) {
                throw new Exception("Яндекс вернул статус ошибки: HTTP " . $response->status());
            }

            $html = $response->body();

            if (str_contains($html, 'captcha__human') || str_contains($html, 'checkbox-captcha')) {
                throw new Exception("Сработала блокировка Anti-Bot Яндекса.");
            }

            preg_match('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $jsonLdMatches);

            if (!empty($jsonLdMatches)) {
                $data = json_decode($jsonLdMatches[1], true);
                if (isset($data['@graph'])) { $data = $data['@graph'][0]; }

                $name = $data['name'] ?? null;
                $address = $data['address']['streetAddress'] ?? ($data['address'] ?? null);
                $rating = $data['aggregateRating']['ratingValue'] ?? 0.0;
                $ratingCount = $data['aggregateRating']['ratingCount'] ?? 0;
                $reviewCount = $data['aggregateRating']['reviewCount'] ?? $ratingCount;
            } else {
                preg_match('/<meta[^>]*itemprop="name"[^>]*content="([^"]*)"/si', $html, $nameMatches);
                preg_match('/<meta[^>]*itemprop="address"[^>]*content="([^"]*)"/si', $html, $addressMatches);
                preg_match('/<meta[^>]*itemprop="ratingValue"[^>]*content="([^"]*)"/si', $html, $ratingMatches);
                preg_match('/<meta[^>]*itemprop="ratingCount"[^>]*content="([^"]*)"/si', $html, $ratingCountMatches);
                preg_match('/<meta[^>]*itemprop="reviewCount"[^>]*content="([^"]*)"/si', $html, $reviewCountMatches);

                $name = $nameMatches[1] ?? null;
                $address = $addressMatches[1] ?? null;
                $rating = $ratingMatches[1] ?? 0.0;
                $ratingCount = $ratingCountMatches[1] ?? 0;
                $reviewCount = $reviewCountMatches[1] ?? 0;
            }

            if (!$name) {
                throw new Exception("Не удалось обнаружить маркеры данных на HTML-странице.");
            }

            $organization->update([
                'name'         => trim(html_entity_decode($name)),
                'address'      => $address ? trim(html_entity_decode($address)) : 'Адрес не указан',
                'rating'       => (float) $rating,
                'rating_count' => (int) $ratingCount,
                'review_count' => (int) $reviewCount,
            ]);

        } catch (Exception $e) {
            logger()->error("Критический сбой parseMeta для ID {$yandexId}: " . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Публичный метод для парсинга одной конкретной страницы отзывов (по 50 штук за раз)
     */
    public function parseReviewPage(Organization $organization, string $yandexId, int $page): int
    {
        $pageSize = 50;
        // Строка склеивается СТРОГО с полным путем к API отзывов Яндекса
        $url = "https://yandex.ru" . $yandexId . "&page=" . $page . "&pageSize=" . $pageSize . "&ranking=by_time";

        try {
            $response = Http::withHeaders($this->getHeaders())->timeout(10)->get($url);

            if ($response->status() === 403 || $response->status() === 429) {
                throw new Exception("Anti-Bot заблокировал запрос отзывов (Капча).");
            }

            if ($response->failed()) return 0;

            $json = $response->json();
            $reviewsList = $json['data']['reviews'] ?? [];

            if (empty($reviewsList)) return 0;

            $count = 0;
            foreach ($reviewsList as $reviewItem) {
                $authorName = $reviewItem['author']['name'] ?? 'Аноним';
                $text       = $reviewItem['text'] ?? '';
                $stars      = (int) ($reviewItem['rating'] ?? 0);
                $timestamp  = $reviewItem['created_at'] ?? time();

                $organization->reviews()->updateOrCreate(
                    ['author_name' => $authorName, 'text' => $text],
                    ['stars' => $stars, 'publish_date' => Carbon::createFromTimestamp($timestamp)->format('Y-m-d')]
                );
                $count++;
            }

            return $count;

        } catch (Exception $e) {
            logger()->error("Ошибка парсинга страницы отзывов {$page} для ID {$yandexId}: " . $e->getMessage());
            return 0;
        }
    }

    private function getHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'ru-RU,ru;q=0.9',
            'Referer' => 'https://yandex.ru',
        ];
    }
}
