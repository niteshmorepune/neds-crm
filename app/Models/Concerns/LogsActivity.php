<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Logs create / update / delete events on a model to the `activities` table:
 * who did it, what model, when, and which fields changed (as JSON).
 *
 * Attributes listed in $activityHidden (e.g. password tokens) are stripped from
 * the recorded changes. Models can also define $activityExcept to ignore noisy
 * columns when deciding whether an update is worth logging.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            $model->recordActivity('created', $model->activityChanges($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->activityChanges($model->getChanges());

            // Nothing meaningful changed (e.g. only timestamps) — skip.
            if ($changes === []) {
                return;
            }

            $model->recordActivity('updated', $changes);
        });

        static::deleted(function (Model $model) {
            $model->recordActivity('deleted', null);
        });
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    protected function recordActivity(string $event, ?array $changes): void
    {
        Activity::create([
            // Activity actors are internal users only. Read the web guard
            // explicitly so a portal-contact-initiated change (portal guard)
            // logs no user rather than a contact id (which isn't a users row).
            'user_id' => Auth::guard('web')->id(),
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'event' => $event,
            'changes' => $changes,
        ]);
    }

    /**
     * Strip sensitive and ignored attributes from a set of changes.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function activityChanges(array $attributes): array
    {
        $hidden = array_merge(
            ['password', 'remember_token'],
            property_exists($this, 'activityHidden') ? $this->activityHidden : [],
            property_exists($this, 'activityExcept') ? $this->activityExcept : [],
            ['created_at', 'updated_at'],
        );

        return collect($attributes)->except($hidden)->all();
    }
}
