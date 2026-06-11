<?php

namespace Database\Seeders;

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentMode;
use App\Enums\TaskStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic cross-module demo data for local/staging walkthroughs. Additive
 * (creates new rows; does not touch existing data) and refuses to run in
 * production so it can never corrupt live data.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoDataSeeder is disabled in production. Skipping.');

            return;
        }

        $this->callOnce(ServicesSeeder::class);
        $services = Service::all();

        $sales = User::factory()->role(UserRole::Sales)->create(['name' => 'Demo Sales', 'email' => 'demo.sales@neds.test']);
        $support = User::factory()->role(UserRole::Support)->create(['name' => 'Demo Support', 'email' => 'demo.support@neds.test']);

        // Clients + contacts.
        $clients = Customer::factory()->count(5)->create()
            ->each(fn (Customer $c) => Contact::factory()->create(['customer_id' => $c->id, 'is_primary' => true]));
        Customer::factory()->inactive()->create(['company_name' => 'Dormant Traders']);

        // Leads — a mix of open and converted (converted ones feed the perf report).
        Lead::factory()->count(8)->create(['owner_id' => $sales->id, 'service_id' => $services->random()->id]);
        Lead::factory()->count(3)->create([
            'owner_id' => $sales->id,
            'status' => LeadStatus::Converted,
            'converted_at' => now()->subDays(rand(1, 20)),
        ]);

        // Deals across stages.
        foreach (DealStage::cases() as $stage) {
            Deal::factory()->count(2)->create([
                'customer_id' => $clients->random()->id,
                'owner_id' => $sales->id,
                'service_id' => $services->random()->id,
                'stage' => $stage,
                'updated_at' => now()->subDays(rand(0, 10)),
            ]);
        }

        // Projects with tasks (some completed for on-time metrics).
        $clients->take(3)->each(function (Customer $client) use ($services, $support) {
            $project = Project::factory()->create([
                'customer_id' => $client->id,
                'service_id' => $services->random()->id,
                'owner_id' => $support->id,
            ]);
            Task::factory()->count(4)->create(['project_id' => $project->id, 'assignee_id' => $support->id, 'due_date' => now()->addDays(3)]);
            Task::factory()->count(3)->create(['project_id' => $project->id, 'assignee_id' => $support->id, 'status' => TaskStatus::Done, 'due_date' => now()->subDay()]);
        });

        // Invoices + payments, including recurring-sourced ones.
        $template = RecurringInvoice::factory()->create(['customer_id' => $clients->first()->id]);
        foreach ($clients as $i => $client) {
            $invoice = Invoice::factory()->create([
                'customer_id' => $client->id,
                'status' => InvoiceStatus::Sent,
                'issue_date' => now()->startOfMonth()->addDays($i),
                'total' => rand(2, 20) * 100000,
                'recurring_invoice_id' => $i === 0 ? $template->id : null,
            ]);
            $invoice->payments()->create(['paid_on' => now(), 'mode' => PaymentMode::Neft->value, 'amount' => (int) ($invoice->total / 2), 'recorded_by' => $sales->id]);
            $invoice->refreshPaymentStatus();
            $invoice->save();
        }

        // Tickets.
        foreach ($clients->take(4) as $client) {
            Ticket::factory()->create([
                'customer_id' => $client->id,
                'assignee_id' => $support->id,
                'status' => collect([TicketStatus::Open, TicketStatus::InProgress, TicketStatus::Resolved])->random(),
                'priority' => collect(TicketPriority::cases())->random(),
            ]);
        }

        // Attendance, calls and daily reports for the two demo staff (this month).
        foreach ([$sales, $support] as $user) {
            foreach (range(1, 8) as $d) {
                Attendance::factory()->create(['user_id' => $user->id, 'date' => now()->subDays($d)->toDateString()]);
            }
            CallLog::factory()->count(6)->create(['user_id' => $user->id, 'callable_type' => Customer::class, 'callable_id' => $clients->random()->id, 'called_at' => now()->subDays(rand(0, 7))]);
            foreach (range(1, 3) as $d) {
                DailyReport::factory()->create(['user_id' => $user->id, 'date' => now()->subDays($d)->toDateString(), 'submitted_at' => now()]);
            }
        }

        $this->command?->info('Demo data seeded: clients, leads, deals, projects, invoices, tickets, attendance, calls, reports.');
    }
}
