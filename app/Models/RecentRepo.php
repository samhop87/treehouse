<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecentRepo extends Model
{
    protected $fillable = [
        'name',
        'path',
        'branch',
        'last_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'last_opened_at' => 'datetime',
        ];
    }

    /**
     * Get recent repos ordered by last opened.
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderByDesc('last_opened_at')->limit($limit);
    }

    /**
     * Check if the repo path still exists on disk.
     */
    public function existsOnDisk(): bool
    {
        return is_dir($this->path) && is_dir("{$this->path}/.git");
    }
}
