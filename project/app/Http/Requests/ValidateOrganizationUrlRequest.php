<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidateOrganizationUrlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'yandex_url' => [
                'required',
                'string',
                'url',
                // Строгая проверка регулярным выражением на формат Яндекс.Карт (/maps/org/)
                'regex:/^https?:\/\/(www\.)?(maps\.)?yandex\.(ru|com|by)\/maps\/org\/([^\/]+\/)?\d+/i'
            ]
        ];
    }

    /**
     * Сообщения об ошибках, которые улетят обратно во Vue при сбое.
     */
    public function messages(): array
    {
        return [
            'yandex_url.required' => 'Поле ссылки на организацию не может быть пустым.',
            'yandex_url.string'   => 'Переданы некорректные данные.',
            'yandex_url.url'      => 'Введенное значение должно быть валидным веб-адресом.',
            'yandex_url.regex'    => 'Укажите прямую ссылку на карточку организации в Яндекс.Картах. Пример: https://yandex.ru',
        ];
    }
}
