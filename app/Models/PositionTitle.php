<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositionTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'legacy_csc_item_id',
        'title',
        'description',
        'salary_grade',
        'campus',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
