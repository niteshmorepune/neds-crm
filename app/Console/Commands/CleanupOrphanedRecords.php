<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Note;
use App\Models\QuotationItem;
use App\Models\QuotationMilestone;
use App\Models\Task;
use App\Models\TicketReply;
use Illuminate\Console\Command;

class CleanupOrphanedRecords extends Command
{
    protected $signature = 'app:cleanup-orphaned-records';

    protected $description = 'Soft-delete deals/invoices/projects/tickets and hard-delete quotations/contacts/notes/call-logs that belong to already-deleted customers. Run once after deploying the cascade-delete feature.';

    public function handle(): int
    {
        $deletedCustomerIds = Customer::onlyTrashed()->pluck('id');

        if ($deletedCustomerIds->isEmpty()) {
            $this->info('No deleted customers found. Nothing to clean up.');

            return self::SUCCESS;
        }

        $this->info("Found {$deletedCustomerIds->count()} deleted customer(s). Cleaning up their related records…");

        // Notes on orphaned deals
        $orphanedDealIds = \App\Models\Deal::whereIn('customer_id', $deletedCustomerIds)
            ->withoutGlobalScopes()->pluck('id');
        Note::where('notable_type', \App\Models\Deal::class)
            ->whereIn('notable_id', $orphanedDealIds)
            ->delete();
        $deals = \App\Models\Deal::withoutGlobalScopes()
            ->whereIn('customer_id', $deletedCustomerIds)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
        $this->line("  Deals soft-deleted: {$deals}");

        // Quotation items & milestones, then quotations
        $quotationIds = \App\Models\Quotation::whereIn('customer_id', $deletedCustomerIds)->pluck('id');
        QuotationItem::whereIn('quotation_id', $quotationIds)->delete();
        QuotationMilestone::whereIn('quotation_id', $quotationIds)->delete();
        $quotations = \App\Models\Quotation::whereIn('customer_id', $deletedCustomerIds)->delete();
        $this->line("  Quotations hard-deleted: {$quotations}");

        // Tasks for orphaned projects, then projects
        $orphanedProjectIds = \App\Models\Project::withoutGlobalScopes()
            ->whereIn('customer_id', $deletedCustomerIds)->pluck('id');
        Task::whereIn('project_id', $orphanedProjectIds)->delete();
        $projects = \App\Models\Project::withoutGlobalScopes()
            ->whereIn('customer_id', $deletedCustomerIds)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
        $this->line("  Projects soft-deleted: {$projects}");

        // Ticket replies, then tickets
        $orphanedTicketIds = \App\Models\Ticket::withoutGlobalScopes()
            ->whereIn('customer_id', $deletedCustomerIds)->pluck('id');
        TicketReply::whereIn('ticket_id', $orphanedTicketIds)->delete();
        $tickets = \App\Models\Ticket::withoutGlobalScopes()
            ->whereIn('customer_id', $deletedCustomerIds)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
        $this->line("  Tickets soft-deleted: {$tickets}");

        // Invoices
        $invoices = \App\Models\Invoice::withoutGlobalScopes()
            ->whereIn('customer_id', $deletedCustomerIds)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
        $this->line("  Invoices soft-deleted: {$invoices}");

        // Contacts, notes on customer, call logs
        $contacts = \App\Models\Contact::whereIn('customer_id', $deletedCustomerIds)->delete();
        $notes = Note::where('notable_type', Customer::class)
            ->whereIn('notable_id', $deletedCustomerIds)
            ->delete();
        $callLogs = \App\Models\CallLog::where('callable_type', Customer::class)
            ->whereIn('callable_id', $deletedCustomerIds)
            ->delete();
        $this->line("  Contacts deleted: {$contacts}, Customer notes deleted: {$notes}, Call logs deleted: {$callLogs}");

        $this->info('Cleanup complete.');

        return self::SUCCESS;
    }
}
