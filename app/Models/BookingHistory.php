<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const ACTION_CREATED = 'created';

    public const ACTION_RESCHEDULED = 'rescheduled';

    public const ACTION_CANCELLED = 'cancelled';

    protected $fillable = [
        'booking_id',
        'user_id',
        'action',
        'old_data',
        'new_data',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
