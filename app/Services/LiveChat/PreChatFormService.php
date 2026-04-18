<?php

namespace App\Services\LiveChat;

use App\Models\PreChatForm;
use Illuminate\Support\Facades\DB;

class PreChatFormService
{
    /**
     * Update a pre-chat form and its fields
     *
     * @param PreChatForm $form
     * @param array $data
     * @return PreChatForm
     */
    public function updateForm(PreChatForm $form, array $data)
    {
        DB::beginTransaction();

        try {
            // Update form attributes
            if (isset($data['enabled'])) {
                $form->fill(['enabled' => $data['enabled']]);
                $form->save();
            }
            if (isset($data['submit_button_text'])) {
                $form->fill(['submit_button_text' => $data['submit_button_text']]);
                $form->save();
            }

            // Update or create fields
            if (isset($data['fields'])) {
                foreach ($data['fields'] as $fieldData) {
                    if (isset($fieldData['id'])) {
                        // Update existing field
                        $field = $form->fields()->find($fieldData['id']);
                        if ($field) {
                            $field->fill(collect($fieldData)->except(['id'])->toArray());
                            $field->save();
                        }
                    } else {
                        // Create new field
                        $form->fields()->create($fieldData);
                    }
                }
            }

            // Delete fields that were removed
            if (isset($data['delete_field_ids'])) {
                $form->fields()->whereIn('id', $data['delete_field_ids'])->delete();
            }

            DB::commit();
            return $form->fresh(['fields']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
