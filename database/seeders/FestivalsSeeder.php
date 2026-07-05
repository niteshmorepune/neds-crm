<?php

namespace Database\Seeders;

use App\Models\Festival;
use Illuminate\Database\Seeder;

class FestivalsSeeder extends Seeder
{
    /**
     * Only fixed-Gregorian-date national holidays — dates that don't shift
     * year to year, so they can be seeded confidently. Lunar/regional
     * festivals (Diwali, Holi, Ganesh Chaturthi, Eid, Navratri, Gudi Padwa)
     * are deliberately NOT seeded here; their exact dates shift yearly and
     * must be added by an admin from an official calendar. Idempotent and
     * production-safe: keyed on name, never deletes admin-added festivals.
     */
    public function run(): void
    {
        $festivals = [
            ['name' => 'Independence Day', 'date' => '2026-08-15'],
            ['name' => 'Gandhi Jayanti', 'date' => '2026-10-02'],
            ['name' => 'Christmas', 'date' => '2026-12-25'],
            ['name' => "New Year's Day", 'date' => '2027-01-01'],
            ['name' => 'Republic Day', 'date' => '2027-01-26'],
            ['name' => 'Maharashtra Day', 'date' => '2027-05-01'],
        ];

        foreach ($festivals as $festival) {
            Festival::updateOrCreate(
                ['name' => $festival['name']],
                ['date' => $festival['date'], 'is_active' => true],
            );
        }
    }
}
