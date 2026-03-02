<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FpSession extends Model
{
    protected $table = 'fp_sessions';

    protected $fillable = [
        'email',
        'token',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(FpTask::class, 'session_id')->orderBy('sort_order');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(FpProject::class, 'session_id')->orderBy('sort_order');
    }
}
