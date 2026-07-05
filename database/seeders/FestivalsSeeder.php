<?php

namespace Database\Seeders;

use App\Models\Festival;
use Illuminate\Database\Seeder;

class FestivalsSeeder extends Seeder
{
    /**
     * Fixed-Gregorian-date national holidays — dates that don't shift year to
     * year, seeded with full confidence.
     */
    private const FIXED_DATE_FESTIVALS = [
        ['name' => 'Independence Day', 'date' => '2026-08-15'],
        ['name' => 'Gandhi Jayanti', 'date' => '2026-10-02'],
        ['name' => 'Christmas', 'date' => '2026-12-25'],
        ['name' => "New Year's Day", 'date' => '2027-01-01'],
        ['name' => 'Republic Day', 'date' => '2027-01-26'],
        ['name' => 'Maharashtra Day', 'date' => '2027-05-01'],
    ];

    /**
     * Remaining lunar/regional festivals of 2026 (today's date at the time
     * these were added: 2026-07-05), cross-checked across multiple calendar
     * sources on 2026-07-05 — see docs/user-guides/admin.md for the sources.
     * Earlier-2026 lunar festivals (Holi, Gudi Padwa, Ram Navami, Eid al-Fitr)
     * are not included since they're already in the past and wouldn't drive
     * anything. THESE DATES DO NOT CARRY FORWARD TO 2027 — lunar/regional
     * dates shift every year, so this list must be re-verified and replaced
     * for next year, not just left in place.
     */
    private const VERIFIED_2026_LUNAR_FESTIVALS = [
        ['name' => 'Eid-e-Milad-un-Nabi', 'date' => '2026-08-26', 'notes' => 'Islamic calendar — exact date depends on moon sighting, confirmed only close to the day. Verify before relying on this for client content.'],
        ['name' => 'Raksha Bandhan', 'date' => '2026-08-28', 'notes' => 'Most sources agree on 28 Aug; one source listed 9 Aug. Double-check a regional panchang before relying on this for client content.'],
        ['name' => 'Janmashtami', 'date' => '2026-09-04', 'notes' => null],
        ['name' => 'Ganesh Chaturthi', 'date' => '2026-09-14', 'notes' => null],
        ['name' => 'Navratri (Ghatasthapana)', 'date' => '2026-10-11', 'notes' => null],
        ['name' => 'Dussehra (Vijayadashami)', 'date' => '2026-10-20', 'notes' => null],
        ['name' => 'Diwali (Lakshmi Puja)', 'date' => '2026-11-08', 'notes' => null],
    ];

    /**
     * Idempotent and production-safe: keyed on name, never deletes
     * admin-added festivals.
     */
    public function run(): void
    {
        foreach ([...self::FIXED_DATE_FESTIVALS, ...self::VERIFIED_2026_LUNAR_FESTIVALS] as $festival) {
            Festival::updateOrCreate(
                ['name' => $festival['name']],
                ['date' => $festival['date'], 'notes' => $festival['notes'] ?? null, 'is_active' => true],
            );
        }
    }
}
