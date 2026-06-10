<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServicesSeeder extends Seeder
{
    /**
     * NEDS service lines (CLAUDE.md). Idempotent and production-safe: keyed on
     * slug, never deletes admin-added services.
     */
    public function run(): void
    {
        $services = [
            'SEO',
            'GMB',
            'Website Development',
            'Social Media',
            'Google Ads',
            'Software Development',
            'AI Automation',
        ];

        foreach ($services as $sort => $name) {
            Service::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $sort + 1, 'is_active' => true],
            );
        }
    }
}
