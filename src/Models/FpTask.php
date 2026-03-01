<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FpTask extends Model
{
    protected $table = 'fp_tasks';

    protected $fillable = [
        'session_id',
        'type',
        'title',
        'completed',
        'sort_order',
        'estimated_pomodoros',
        'completed_pomodoros',
    ];

    protected $casts = [
        'completed'           => 'boolean',
        'sort_order'          => 'integer',
        'estimated_pomodoros' => 'integer',
        'completed_pomodoros' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(FpSession::class, 'session_id');
    }
}
