<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateOrganizationUrlRequest;
use App\Services\YandexMapsParserService;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    protected $parserService;

    public function __construct(YandexMapsParserService $parserService)
    {
        $this->parserService = $parserService;
    }

    /**
     * Шаг 1: Первичный быстрый импорт (Мета + Страница 1 отзывов)
     */
    public function import(ValidateOrganizationUrlRequest $request): JsonResponse
    {
        $url = $request->input('yandex_url');

        // Ищем в ссылке подстроку /org/ и забираем строго цифры ID из первой группы ()
        if (preg_match('/\/org\/.*?(\d+)/i', $url, $matches)) {
            $yandexId = $matches[1]; // БЕРЕМ ИНДЕКС 1 (СТРОГО ЧИСЛО)
        } else {
            $yandexId = null;
        }


        if (!$yandexId) {
            return response()->json(['errors' => ['yandex_url' => ['Не удалось извлечь ID организации.']]], 422);
        }

        try {
            $organization = Organization::updateOrCreate(
                ['user_id' => $request->user()->id, 'yandex_id' => $yandexId],
                ['yandex_url' => $url]
            );

            $this->parserService->parseInitial($organization, $yandexId);

            return response()->json([
                'success' => true,
                'message' => 'Организация успешно добавлена! Начинается фоновый сбор отзывов.',
                'data'    => $organization->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Не удалось спарсить Яндекс.Карты.',
                'error_detail' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * ТРЕБУЕМЫЙ МЕТОД ДЛЯ ИНТЕРФЕЙСА (Пагинация):
     * Выводит отзывы из локального кэша СУБД порциями по 15 штук без перезагрузки страницы.
     */
    public function getReviews(Organization $organization): JsonResponse
    {
        // Достает данные напрямую из PostgreSQL по 15 элементов на страницу
        $reviews = $organization->reviews()
            ->orderBy('publish_date', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'reviews' => $reviews
        ]);
    }

    /**
     * Потоковый добор страниц (со 2 по 12) через AJAX-чанки
     */
    public function parseChunk(Request $request, Organization $organization): JsonResponse
    {
        $page = (int) $request->input('page', 2);
        
        try {
            $fetched = $this->parserService->parseReviewPage($organization, $organization->yandex_id, $page);

            return response()->json([
                'success' => true,
                'page' => $page,
                'fetched' => $fetched,
                'total_in_db' => $organization->reviews()->count()
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Получить список всех сохраненных организаций пользователя
     */
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()->organizations()->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $organizations]);
    }
}
