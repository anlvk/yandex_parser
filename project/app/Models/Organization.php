<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'user_id',
        'yandex_id',
        'yandex_url',
        'name',
        'address',
        'rating',        // Добавлено
        'rating_count',  // Добавлено
        'review_count',  // Добавлено
        'phone',
        'working_hours'
    ];


    /**
     * СВЯЗЬ: У одной организации может быть много отзывов (до 600 штук)
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Связь: организация принадлежит конкретному пользователю.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
