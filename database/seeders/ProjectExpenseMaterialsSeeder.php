<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectExpenseMaterialsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('project_expenses')->whereNotNull('category')->whereNotNull('description')
            ->select('id', 'category', 'description')->orderBy('id')->chunkById(500, function ($rows) {
                $now = now();
                $pairs = [];
                foreach ($rows as $row) {
                    $category = trim($row->category);
                    $description = trim($row->description);
                    if ($category !== '' && $description !== '') {
                        $pairs[$category."\0".$description] = ['category' => $category, 'description' => $description, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
                foreach ($pairs as $pair) {
                    DB::table('materials')->insertOrIgnore($pair);
                }
            });
    }
}
