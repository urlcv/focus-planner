<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FpProject extends Model
{
    protected $table = 'fp_projects';

    protected $fillable = [
        'session_id',
        'name',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(FpSession::class, 'session_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FpTask::class, 'project_id');
    }
}
