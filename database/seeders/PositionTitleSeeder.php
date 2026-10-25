<?php

namespace Database\Seeders;

use App\Models\PositionTitle;
use Illuminate\Database\Seeder;

class PositionTitleSeeder extends Seeder
{
    public function run(): void
    {
        PositionTitle::updateOrCreate(
            ['title' => 'Department Head'],
            ['is_active' => true],
        );
    }
}
