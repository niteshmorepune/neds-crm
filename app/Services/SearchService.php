<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Global search across the core records. Each section is gated by the same menu
 * access the sidebar/routes use (so results never leak a record the user can't
 * open), and row-level visibility scopes are applied where a model defines one.
 */
class SearchService
{
    private const PER_SECTION = 8;

    public function __construct(private readonly MenuResolver $menu) {}

    /**
     * @return array<int, array{type: string, results: array<int, array{label: string, sub: string, url: string}>}>
     */
    public function search(User $user, string $term): array
    {
        $term = trim($term);

        if (Str::length($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';
        $sections = [];

        if ($this->menu->canAccess($user, 'customer')) {
            $sections[] = $this->section('Clients', Customer::query()->visibleTo($user)
                ->where(fn ($q) => $q->where('company_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                ->limit(self::PER_SECTION)->get()
                ->map(fn (Customer $c) => ['label' => $c->company_name, 'sub' => $c->email ?? '', 'url' => route('clients.show', $c)]));
        }

        if ($this->menu->canAccess($user, 'lead-generation')) {
            $sections[] = $this->section('Leads', Lead::query()->visibleTo($user)
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('company', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(self::PER_SECTION)->get()
                ->map(fn (Lead $l) => ['label' => $l->name, 'sub' => $l->company ?? '', 'url' => route('leads.show', $l)]));
        }

        if ($this->menu->canAccess($user, 'sales-department')) {
            $sections[] = $this->section('Deals', Deal::query()->visibleTo($user)
                ->where('title', 'like', $like)
                ->limit(self::PER_SECTION)->get()
                ->map(fn (Deal $d) => ['label' => $d->title, 'sub' => $d->stage->label(), 'url' => route('deals.show', $d)]));
        }

        if ($this->menu->canAccess($user, 'invoices')) {
            $sections[] = $this->section('Invoices', Invoice::query()->with('customer')
                ->where('invoice_number', 'like', $like)
                ->limit(self::PER_SECTION)->get()
                ->map(fn (Invoice $i) => ['label' => $i->invoice_number, 'sub' => $i->customer?->company_name ?? '', 'url' => route('invoices.show', $i)]));
        }

        if ($this->menu->canAccess($user, 'tickets')) {
            $sections[] = $this->section('Tickets', Ticket::query()->visibleTo($user)
                ->where('subject', 'like', $like)
                ->limit(self::PER_SECTION)->get()
                ->map(fn (Ticket $t) => ['label' => $t->subject, 'sub' => $t->status->label(), 'url' => route('tickets.show', $t)]));
        }

        if ($this->menu->canAccess($user, 'project-updates')) {
            $sections[] = $this->section('Projects', Project::query()->with('customer')
                ->where('name', 'like', $like)
                ->limit(self::PER_SECTION)->get()
                ->map(fn (Project $p) => ['label' => $p->name, 'sub' => $p->customer?->company_name ?? '', 'url' => route('projects.show', $p)]));
        }

        // Drop empty sections.
        return array_values(array_filter($sections, fn ($s) => $s['results'] !== []));
    }

    private function section(string $type, $results): array
    {
        return ['type' => $type, 'results' => $results->all()];
    }
}
