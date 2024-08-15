<?php

namespace App\Models;

use App\Enums\ProtocolStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @OA\Schema(
 *     schema="Protocol",
 *     type="object",
 *     title="Протокол",
 *     description="Модель протокола",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="ID протокола"
 *     ),
 *     @OA\Property(
 *          property="protocol_number",
 *          type="integer",
 *          description="Номер протокола"
 *      ),
 *     @OA\Property(
 *         property="theme",
 *         type="string",
 *         description="Тема протокола"
 *     ),
 *     @OA\Property(
 *         property="agenda",
 *         type="string",
 *         description="Повестка дня"
 *     ),
 *     @OA\Property(
 *         property="secretary_id",
 *         type="integer",
 *         description="ID секретаря"
 *     ),
 *     @OA\Property(
 *         property="director_id",
 *         type="integer",
 *         description="ID директора"
 *     ),
 *     @OA\Property(
 *         property="event_date",
 *         type="string",
 *         format="date",
 *         description="Дата события"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         description="Статус протокола"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Дата и время создания протокола"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Дата и время последнего обновления протокола"
 *     )
 * )
 *
 */

class Protocol extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'theme',
        'agenda',
        'event_date',
        'secretary_id',
        'director_id',
        'creator_id',
        'status',
        'stage',
        'transcript',
        'final_transcript',
        'user_protocol_number',
        'location',
        'city',
        'event_start_time',
    ];

    protected $casts = [
        'status' => ProtocolStatusEnum::class,
        'event_start_time' => 'datetime',
        'event_date' => 'datetime',
        'final_transcript' => 'array',
    ];

    public function getTranscriptAttribute($value): array
    {
        return json_decode($value, true) ?: [];
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProtocolMember::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProtocolTask::class);
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
