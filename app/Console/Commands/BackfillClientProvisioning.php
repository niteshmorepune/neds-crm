<?php

namespace App\Console\Commands;

use App\Enums\CustomerStatus;
use App\Jobs\ProvisionClientExternallyJob;
use App\Models\Customer;
use Illuminate\Console\Command;

class BackfillClientProvisioning extends Command
{
    protected $signature = 'app:backfill-client-provisioning';

    protected $description = 'Queue Drishti/SMDost provisioning for active clients whose deal was won before the auto-provisioning hook existed (one-off correction).';

    public function handle(): int
    {
        $customers = Customer::where('status', CustomerStatus::Active->value)
            ->whereNull('drishti_client_id')
            ->whereNull('smdost_client_id')
            ->get(['id', 'company_name']);

        $customers->each(function (Customer $customer) {
            ProvisionClientExternallyJob::dispatch($customer->id);
            $this->line("Queued #{$customer->id} ({$customer->company_name})");
        });

        $this->info("Queued provisioning for {$customers->count()} client(s).");

        return self::SUCCESS;
    }
}
