<?php

namespace App\Services\LiveChat;

use App\Models\PostChatForm;
use Illuminate\Support\Facades\DB;

class PostChatFormService
{
    /**
     * Update a pre-chat form and its fields
     *
     * @param PostChatForm $form
     * @param array $data
     * @return PostChatForm
     */
    public function updateFormold(PostChatForm $form, array $data)
    {
        DB::beginTransaction();
        try {
            //  dd($data['form']);
            // Update form attributes
            if (isset($data['form']['enabled'])) {
                $form->enabled = $data['form']['enabled'];
            }

            if (isset($data['form']['submit_button_text'])) {
                $form->submit_button_text = $data['form']['submit_button_text'];
            }

            $form->save();

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

    public function updateForm(PostChatForm $form, array $data)
    {
        DB::beginTransaction();

        try {
            // ✅ Support flat OR nested form payload
            $formData = $data['form'] ?? $data;

            if (array_key_exists('enabled', $formData)) {
                $form->enabled = $formData['enabled'];
            }

            if (array_key_exists('submit_button_text', $formData)) {
                $form->submit_button_text = $formData['submit_button_text'];
            }

            $form->save();

            // Update or create fields
            if (isset($data['fields'])) {
                foreach ($data['fields'] as $fieldData) {
                    if (isset($fieldData['id'])) {
                        $field = $form->fields()->find($fieldData['id']);
                        if ($field) {
                            $field->fill(
                                collect($fieldData)->except(['id'])->toArray()
                            );
                            $field->save();
                        }
                    } else {
                        $form->fields()->create($fieldData);
                    }
                }
            }

            // Delete removed fields
            if (isset($data['delete_field_ids'])) {
                $form->fields()
                    ->whereIn('id', $data['delete_field_ids'])
                    ->delete();
            }

            DB::commit();

            return $form->fresh(['fields']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
