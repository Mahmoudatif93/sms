<?php

namespace App\Jobs;

use App\Models\AttributeDefinition;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Models\IAMList;
use App\Models\ContactList;
use App\Traits\ContactManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessListContactsJob implements ShouldQueue
{
    use Queueable, ContactManager;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;

    protected string $listId;
    protected string $organizationId;
    protected array $phoneNumbers;
    protected ?string $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $listId, string $organizationId, array $phoneNumbers, ?string $userId = null)
    {
        $this->listId = $listId;
        $this->organizationId = $organizationId;
        $this->phoneNumbers = $phoneNumbers;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        echo "📞 Total phone numbers to process: " . count($this->phoneNumbers) . "\n\n";
        try {

            $contactsToInsert = [];
            $identifiersToInsert = [];
            $attributesToInsert = [];
            $now = now();

            // Find the list
            $list = IAMList::find($this->listId);
            if (!$list) {
                throw new \Exception("List not found: {$this->listId}");
            }
            // Get attribute definitions once (same as BulkImportContactsJob)
            $whatsappDef = cache()->remember("attr_def_{$this->organizationId}_subscribed-whatsapp", 3600, function () {
                return AttributeDefinition::forOrgOrGlobal($this->organizationId)->where('key', 'subscribed-whatsapp')->first();
            });
            
            $definitions = cache()->remember("attr_defs_{$this->organizationId}_all", 3600, function () {
                return AttributeDefinition::forOrgOrGlobal($this->organizationId)
                    ->get()
                    ->keyBy('key');
            });


            // Process raw phone numbers using ContactManager trait
            echo "📞 [" . date('Y-m-d H:i:s') . "] Starting phone number processing...\n";

            $processedContacts = $this->processPhoneNumbersArray($this->phoneNumbers, $this->organizationId);
            // dd($processedContacts);
            $processed = 0;
            $phones = array_map(fn($r) => $r['normalized_phone'] ?? null, $processedContacts);
            $phones = array_filter($phones);

            $existingContacts = ContactEntity::where('organization_id', $this->organizationId)
                ->whereHas(
                    'identifiers',
                    fn($q) =>
                    $q->where('key', 'phone-number')->whereIn('value', $phones)
                )
                ->with(['identifiers' => fn($q) => $q->where('key', 'phone-number')])
                ->get();
            // $existingContacts = \DB::table('identifiers as i')
            //     ->join('contacts as c', 'c.id', '=', 'i.contact_id')
            //     ->where('i.key', 'phone-number')
            //     ->where('c.organization_id', $this->organizationId)
            //     ->whereIn('i.value', $phones)
            //     ->pluck('c.id', 'i.value')
            //     ->toArray();

            $existingMap = [];
            foreach ($existingContacts as $c) {
                $phoneValue = $c->identifiers->first()->value;
                $existingMap[$phoneValue] = $c->id;
            }
            foreach ($processedContacts as $contactData) {
                try {
                    $processed++;

                    if (!$contactData['is_valid']) {
                        echo "❌ [" . date('Y-m-d H:i:s') . "] Invalid phone: " . ($contactData['error'] ?? 'Unknown error') . "\n";
                        continue;
                    }

                    $phone = $contactData['normalized_phone'];
                    if (!isset($existingMap[$phone])) {
                        $uuid = (string) Str::uuid();

                        $contactsToInsert[] = [
                            'id' => $uuid,
                            'organization_id' => $this->organizationId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $identifiersToInsert[] = [
                            'contact_id' => $uuid,
                            'key' => 'phone-number',
                            'value' => $phone,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $contactId = $uuid;
                        $existingMap[$phone] = $uuid;
                    } else {
                        $contactId = $existingMap[$phone];
                    }

                    if ($whatsappDef) {
                        $attributesToInsert[] = [
                            'contact_id' => $contactId,
                            'attribute_definition_id' => $whatsappDef->id,
                            'value' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    foreach (($contactData['raw'] ?? []) as $key => $value) {
                        if (!$value || $key !== 'phone-number') {
                            continue;
                        }
                        if ($definitions->has($key)) {
                            $attributesToInsert[] = [
                                'contact_id' => $contactId,
                                'attribute_definition_id' => $definitions[$key]->id,
                                'value' => $value,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }






                } catch (\Exception $e) {
                    throw $e;
                }

            }

            if (!empty($contactsToInsert)) {
                \DB::table('contacts')->insert($contactsToInsert);
            }
            if (!empty($identifiersToInsert)) {
                \DB::table('identifiers')->insert($identifiersToInsert);
            }

            if (!empty($attributesToInsert)) {
                \DB::table('contact_attributes')->insertOrIgnore($attributesToInsert);
            }
            $contactIds = array_values($existingMap);
            if (!empty($contactIds)) {
                ContactList::insertOrIgnore(
                    collect($contactIds)->map(fn($id) => [
                        'list_id' => $this->listId,
                        'contact_id' => $id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->toArray()
                );
            }

            $list->updateProgress(1);

            // Mark list as active (completed)

        } catch (\Exception $e) {
            // Mark list as failed
            $list = IAMList::find($this->listId);
            if ($list) {
                $list->markAsFailed($e->getMessage());
            }

            Log::error('List contacts processing job failed', [
                'list_id' => $this->listId,
                'organization_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'user_id' => $this->userId
            ]);

            throw $e;
        }
    }

}
