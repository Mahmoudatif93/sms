<?php

namespace App\Jobs;

use App\Models\AttributeDefinition;
use App\Models\BulkImportLog;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Rules\WhatsappValidPhoneNumber;
use App\Traits\ContactManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Validator;

class BulkImportContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ContactManager;

    protected $organizationId;
    protected $phoneNumbers;
    protected $userId;
    protected $importLogId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $organizationId, array $phoneNumbers, ?string $userId = null, ?int $importLogId = null)
    {
        $this->organizationId = $organizationId;
        $this->phoneNumbers = $phoneNumbers;
        $this->userId = $userId;
        $this->importLogId = $importLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $importLog = null;

        // Get or create import log
        if ($this->importLogId) {
            $importLog = BulkImportLog::find($this->importLogId);
        }

        if (!$importLog) {
            $importLog = BulkImportLog::create([
                'organization_id' => $this->organizationId,
                'user_id' => $this->userId,
                'status' => BulkImportLog::STATUS_PROCESSING,
                'total_records' => count($this->phoneNumbers),
                'started_at' => now(),
            ]);
        } else {
            $importLog->markAsProcessing();
        }


        $created = [];
        $invalid = [];
        $processed = 0;

        // Get attribute definitions once

        $whatsappDef = cache()->remember("attr_def_{$this->organizationId}_subscribed-whatsapp", 3600, function () {
            return AttributeDefinition::forOrgOrGlobal($this->organizationId)->where('key', 'subscribed-whatsapp')->first();
        });

        $firstNameDef = cache()->remember("attr_def_{$this->organizationId}_first-name", 3600, function () {
            return AttributeDefinition::forOrgOrGlobal($this->organizationId)->where('key', 'first-name')->first();
        });

        $lastNameDef = cache()->remember("attr_def_{$this->organizationId}_last-name", 3600, function () {
            return AttributeDefinition::forOrgOrGlobal($this->organizationId)->where('key', 'last-name')->first();
        });

        $displayNameDef = cache()->remember("attr_def_{$this->organizationId}_display-name", 3600, function () {
            return AttributeDefinition::forOrgOrGlobal($this->organizationId)->where('key', 'display-name')->first();
        });


        // Process raw phone numbers using ContactManager trait

        $processedContacts = $this->processPhoneNumbersArray($this->phoneNumbers, $this->organizationId);



        foreach ($processedContacts as $contactData) {
            try {
                $processed++;

                if (!$contactData['is_valid']) {
                    $invalid[] = [
                        'raw' => $contactData['raw'],
                        'normalized_phone' => $contactData['normalized_phone'],
                        'error' => $contactData['error'],
                    ];
                    continue;
                }

                $phone = $contactData['normalized_phone'];
                // Skip if contact already exists (already checked in processPhoneNumbersArray)
                if ($contactData['contact']) {
                    continue;
                }

                // Create new contact
                $contact = ContactEntity::create([
                    'id' => Str::uuid(),
                    'organization_id' => $this->organizationId
                ]);

                // Add phone identifier
                $contact->identifiers()->create([
                    'key' => 'phone-number',
                    'value' => $phone
                ]);

                // Add WhatsApp subscription attribute
                if ($whatsappDef) {
                    ContactAttribute::create([
                        'contact_id' => $contact->id,
                        'attribute_definition_id' => $whatsappDef->id,
                        'value' => true,
                    ]);
                }

                // Add name attributes if available
                if (!empty($contactData['raw']['first-name']) && $firstNameDef) {
                    ContactAttribute::create([
                        'contact_id' => $contact->id,
                        'attribute_definition_id' => $firstNameDef->id,
                        'value' => $contactData['raw']['first-name'],
                    ]);
                }

                if (!empty($contactData['raw']['last-name']) && $lastNameDef) {
                    ContactAttribute::create([
                        'contact_id' => $contact->id,
                        'attribute_definition_id' => $lastNameDef->id,
                        'value' => $contactData['raw']['last-name'],
                    ]);
                }

                if (!empty($contactData['raw']['display-name']) && $displayNameDef) {
                    ContactAttribute::create([
                        'contact_id' => $contact->id,
                        'attribute_definition_id' => $displayNameDef->id,
                        'value' => $contactData['raw']['display-name'],
                    ]);
                }

                $created[] = $contact;

                // Log progress every 100 records and update import log
                if ($processed % 100 === 0) {
                    $importLog->updateProgress($processed);
                }

            } catch (\Exception $e) {
                Log::error('Error processing contact in bulk import', [
                    'organization_id' => $this->organizationId,
                    'phone_data' => $contactData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'import_log_id' => $importLog->id
                ]);

                $invalid[] = [
                    'raw' => $contactData['raw'] ?? $contactData,
                    'normalized_phone' => $contactData['normalized_phone'] ?? null,
                    'error' => 'Processing error: ' . $e->getMessage(),
                ];
            }
        }


        // Mark import as completed
        $importLog->markAsCompleted(count($created), count($invalid), $invalid);
        Log::info('Bulk import job completed', [
            'organization_id' => $this->organizationId,
            'total_processed' => $processed,
            'created_count' => count($created),
            'invalid_count' => count($invalid),
            'user_id' => $this->userId,
            'import_log_id' => $importLog->id,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Update import log if exists
        if ($this->importLogId) {
            $importLog = BulkImportLog::find($this->importLogId);
            if ($importLog) {
                $importLog->markAsFailed($exception->getMessage());
            }
        }

        Log::error('Bulk import job failed', [
            'organization_id' => $this->organizationId,
            'total_numbers' => count($this->phoneNumbers),
            'user_id' => $this->userId,
            'import_log_id' => $this->importLogId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
