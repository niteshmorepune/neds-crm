<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncContactNameToWadeskJob;
use App\Models\Contact;
use App\Models\Lead;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * wadesk.in -> CRM half of the two-way contact-name sync (2026-09-23):
 * called when a staff member renames a Contact in wadesk.in's own UI, so
 * the matching open Lead and any Client Contact with that phone take the
 * same name. Owner-reported: a name corrected in wadesk.in didn't reach the
 * CRM, so the team saw two different names for one person. Every matching
 * open Lead is renamed (not just the first), so an unmerged duplicate
 * doesn't keep the stale name.
 *
 * Deliberately never touches Customer.company_name — a wadesk.in contact is
 * a person, not a company. Matching is by last 10 digits, the same key every
 * other wadesk<->CRM bridge uses. The rename is applied inside
 * SyncContactNameToWadeskJob::withoutPushing() so it isn't echoed straight
 * back to wadesk.in. Regular model events still fire, so activity logging
 * records the change as usual.
 */
class WadeskContactNameController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($data['name']);
        $last10 = Phone::last10($data['phone']);

        if ($name === '' || strlen($last10) < 10) {
            return response()->json(['status' => 'ignored']);
        }

        // Normalized match (not Lead::findOpenByPhone()'s raw LIKE) — stored
        // numbers are often formatted ("94217 60797"), same reason as
        // Phone::normalizedSql()'s own list-search use.
        $pattern = '%'.$last10;

        return SyncContactNameToWadeskJob::withoutPushing(function () use ($name, $pattern) {
            $leads = Lead::whereIn('status', LeadStatus::openValues())
                ->where(fn (Builder $query) => $query
                    ->whereRaw(Phone::normalizedSql('phone').' LIKE ?', [$pattern])
                    ->orWhereRaw(Phone::normalizedSql('alternate_phone').' LIKE ?', [$pattern]))
                ->where('name', '!=', $name)
                ->get();

            $contacts = Contact::whereRaw(Phone::normalizedSql('phone').' LIKE ?', [$pattern])
                ->where('name', '!=', $name)
                ->get();

            $leads->each->update(['name' => $name]);
            $contacts->each->update(['name' => $name]);

            return response()->json([
                'status' => 'ok',
                'leads_updated' => $leads->count(),
                'contacts_updated' => $contacts->count(),
            ]);
        });
    }
}
