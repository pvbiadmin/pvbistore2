<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'point_id', 'type', 'points', 'details',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
