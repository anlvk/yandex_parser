<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    // Указываем ваши реальные колонки из миграции
    protected $fillable = [
        'organization_id',
        'author_name',
        'stars',
        'text',
        'publish_date'
    ];

    /**
     * Связь: отзыв принадлежит организации
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
