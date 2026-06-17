<?php

namespace App\Livewire;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Rules\Gstin;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ClientImport extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public $file;

    /** @var array<int, string> */
    public array $headers = [];

    /** @var array<int, array<int, string>> */
    public array $rows = [];

    /** @var array<string, string> field => header index ('' = skip) */
    public array $mapping = [];

    /** @var array{imported:int, skipped:array<int, array{row:int, reason:string}>, errors:array<int, array{row:int, message:string}>} */
    public array $results = ['imported' => 0, 'skipped' => [], 'errors' => []];

    /**
     * Mappable target fields => human label.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'company_name' => 'Company name (required)',
            'email' => 'Email',
            'phone' => 'Phone',
            'gstin' => 'GSTIN',
            'website' => 'Website',
            'address_line1' => 'Address',
            'city' => 'City',
            'state_code' => 'State code',
            'pincode' => 'Pincode',
            'status' => 'Status',
        ];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('create', Customer::class), 403);
    }

    public function parse(): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $handle = fopen($this->file->getRealPath(), 'r');
        $this->headers = array_map('trim', (array) fgetcsv($handle));

        $this->rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            // Skip fully empty lines.
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $this->rows[] = $row;
        }
        fclose($handle);

        $this->mapping = $this->guessMapping();
        $this->file = null;
        $this->step = 2;
    }

    public function import(): void
    {
        abort_unless(auth()->user()?->can('create', Customer::class), 403);

        if (($this->mapping['company_name'] ?? '') === '') {
            $this->addError('mapping', 'Map a column to "Company name" before importing.');

            return;
        }

        $results = ['imported' => 0, 'skipped' => [], 'errors' => []];
        $seenEmails = [];
        $seenGstins = [];

        foreach ($this->rows as $i => $row) {
            $line = $i + 2; // +1 header, +1 for 1-based display
            $data = $this->mapRow($row);

            $validator = Validator::make($data, [
                'company_name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'gstin' => ['nullable', 'string', 'size:15', new Gstin],
                'state_code' => ['nullable', 'in:'.implode(',', array_keys(config('india.states')))],
            ]);

            if ($validator->fails()) {
                $results['errors'][] = ['row' => $line, 'message' => $validator->errors()->first()];

                continue;
            }

            // Skip duplicates by email or GSTIN — within the file and against the DB.
            $email = $data['email'] ? strtolower($data['email']) : null;
            $gstin = $data['gstin'] ?: null;

            if ($email && (in_array($email, $seenEmails, true) || Customer::where('email', $data['email'])->exists())) {
                $results['skipped'][] = ['row' => $line, 'reason' => "Duplicate email: {$data['email']}"];

                continue;
            }
            if ($gstin && (in_array($gstin, $seenGstins, true) || Customer::where('gstin', $gstin)->exists())) {
                $results['skipped'][] = ['row' => $line, 'reason' => "Duplicate GSTIN: {$gstin}"];

                continue;
            }

            Customer::create([
                'company_name' => $data['company_name'],
                'email' => $data['email'] ?: null,
                'phone' => $data['phone'] ?: null,
                'gstin' => $gstin,
                'website' => $data['website'] ?: null,
                'address_line1' => $data['address_line1'] ?: null,
                'city' => $data['city'] ?: null,
                'state_code' => $data['state_code'] ?: null,
                'state' => $data['state_code'] ? config("india.states.{$data['state_code']}") : null,
                'pincode' => $data['pincode'] ?: null,
                'status' => $this->normaliseStatus($data['status']),
                'owner_id' => auth()->id(),
            ]);

            if ($email) {
                $seenEmails[] = $email;
            }
            if ($gstin) {
                $seenGstins[] = $gstin;
            }
            $results['imported']++;
        }

        $this->results = $results;
        $this->step = 3;
    }

    public function startOver(): void
    {
        $this->reset(['step', 'file', 'headers', 'rows', 'mapping', 'results']);
        $this->step = 1;
        $this->results = ['imported' => 0, 'skipped' => [], 'errors' => []];
    }

    public function render()
    {
        return view('livewire.client-import');
    }

    /**
     * Map one CSV row to a target-field => value array using $this->mapping.
     *
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function mapRow(array $row): array
    {
        $data = [];
        foreach (array_keys($this->fields()) as $field) {
            $index = $this->mapping[$field] ?? '';
            $data[$field] = $index === '' ? '' : trim((string) ($row[(int) $index] ?? ''));
        }

        return $data;
    }

    /**
     * Best-effort auto-match of CSV headers to target fields.
     *
     * @return array<string, string>
     */
    private function guessMapping(): array
    {
        $mapping = [];
        foreach (array_keys($this->fields()) as $field) {
            $mapping[$field] = '';
            $needle = str_replace('_', '', $field);
            foreach ($this->headers as $index => $header) {
                $haystack = str_replace([' ', '_', '-'], '', strtolower($header));
                if ($haystack === $needle || str_contains($haystack, $needle)) {
                    $mapping[$field] = (string) $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function normaliseStatus(string $value): string
    {
        return CustomerStatus::tryFrom(strtolower(trim($value)))?->value
            ?? CustomerStatus::Active->value;
    }
}
