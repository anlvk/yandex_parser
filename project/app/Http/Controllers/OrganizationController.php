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

    // Внедряем наш выделенный сервис через конструктор
    public function __construct(YandexMapsParserService $parserService)
    {
        $this->parserService = $parserService;
    }

    /**
     * Основная точка импорта
     */
    public function import(ValidateOrganizationUrlRequest $request): JsonResponse
    {
        $url = $request->input('yandex_url');

        preg_match('/\/org\/([^\/]+\/)?(\d+)/', $url, $matches);
        $yandexId = $matches[2] ?? null;

        if (!$yandexId) {
            return response()->json(['errors' => ['yandex_url' => ['Ссылка валидна, но не удалось извлечь ID организации.']]], 422);
        }

        try {
            // Создаем пустую сущность организации
            $organization = Organization::updateOrCreate(
                ['user_id' => $request->user()->id, 'yandex_id' => $yandexId],
                ['yandex_url' => $url]
            );

            // Передаем управление в сервис
            $this->parserService->parse($organization, $yandexId);

            return response()->json([
                'success' => true,
                'message' => 'Все данные (до 600 отзывов) успешно сохранены во внутренний кэш БД!',
                'data'    => $organization->fresh()
            ]);

        } catch (\Exception $e) {
            // Осмысленная обработка ошибок для интерфейса Vue
            return response()->json([
                'message' => 'Не удалось спарсить Яндекс.Карты. Возможно, сработала защита от ботов или изменилась верстка.',
                'error_detail' => $e->getMessage()
            ], 502); // Код 502 (Bad Gateway) отлично подходит для ошибок внешних источников
        }
    }

    /**
     * Постраничная пагинация порциями по 15 штук
     */
    public function getReviews(Organization $organization): JsonResponse
    {
        $reviews = $organization->reviews()
            ->orderBy('publish_date', 'desc')
            ->paginate(15); // Жесткое требование вывода

        return response()->json([
            'success' => true,
            'reviews' => $reviews
        ]);
    }

    /**
     * Получить список всех компаний пользователя (для Блока 2 фронтенда)
     */
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()->organizations()->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $organizations]);
    }
}
