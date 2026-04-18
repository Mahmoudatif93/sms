<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationTemplateInterface;
use App\Models\NotificationTemplate;
use App\Notifications\Core\NotificationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class TemplateManager
{
    protected array $registeredTemplates = [];
    protected array $templateCache = [];

    /**
     * Register a template
     */
    public function registerTemplate(string $id, NotificationTemplateInterface $template): self
    {
        $this->registeredTemplates[$id] = $template;
        
        Log::info("Template registered", [
            'template_id' => $id,
            'template_name' => $template->getName(),
            'template_type' => $template->getType()
        ]);

        return $this;
    }

    /**
     * Get a template by ID
     */
    public function getTemplate(string $id): ?NotificationTemplateInterface
    {
        // Check registered templates first
        if (isset($this->registeredTemplates[$id])) {
            return $this->registeredTemplates[$id];
        }

        // Check database templates
        $cacheKey = "notification_template_{$id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($id) {
            return NotificationTemplate::active()->find($id);
        });
    }

    /**
     * Create a message from template
     */
    public function createFromTemplate(
        string $templateId,
        array $variables = [],
        array $recipients = [],
        array $channels = [],
        string $locale = 'ar'
    ): NotificationMessage {
       
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }
        if (!$template->validateVariables($variables)) {
            $missing = array_diff($template->getRequiredVariables(), array_keys($variables));
            throw new Exception("Missing required variables: " . implode(', ', $missing));
        }
        // Determine channels to use
        if (empty($channels)) {
            $channels = $template->getSupportedChannels();
        } else {
            // Filter channels to only supported ones
            $channels = array_intersect($channels, $template->getSupportedChannels());
        }

        if (empty($channels)) {
            throw new Exception("No supported channels available for template: {$templateId}");
        }

        // Create message with multi-channel content
        $primaryChannel = $channels[0];
        $message = $template->render($variables, $primaryChannel, $locale);
        // Set recipients
        $message->setRecipients($recipients);

        // Add channel-specific content for each channel
        $channelContents = [];
        foreach ($channels as $channel) {
            $content = $template->getContent($channel, $locale);
            $title = $this->replaceVariables($content['title'] ?? '', $variables);
            $body = $this->replaceVariables($content['body'] ?? '', $variables);

            $channelContents[$channel] = [
                'title' => $title,
                'body' => $body,
                'template' => $content['template'] ?? null
            ];

            $message->addChannel($channel);
        }

        // Store channel-specific content in message metadata
        $message->setData(array_merge($message->getData(), [
            'channel_contents' => $channelContents,
            'template_id' => $templateId,
            'locale' => $locale
        ]));

        Log::info("Message created from template", [
            'template_id' => $templateId,
            'message_id' => $message->getId(),
            'channels' => $channels,
            'locale' => $locale,
            'recipients_count' => count($recipients),
            'channel_contents_count' => count($channelContents)
        ]);

        return $message;
    }

    /**
     * Replace variables in content
     */
    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $value, $content);
        }

        return $content;
    }

    /**
     * Render template content for specific channel and locale
     */
    public function renderTemplate(
        string $templateId,
        array $variables,
        string $channel = 'sms',
        string $locale = 'ar'
    ): array {
        $template = $this->getTemplate($templateId);
        
        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }

        if (!$template->supportsChannel($channel)) {
            throw new Exception("Template {$templateId} does not support channel: {$channel}");
        }

        if (!$template->supportsLocale($locale)) {
            throw new Exception("Template {$templateId} does not support locale: {$locale}");
        }

        if (!$template->validateVariables($variables)) {
            $missing = array_diff($template->getRequiredVariables(), array_keys($variables));
            throw new Exception("Missing required variables: " . implode(', ', $missing));
        }

        $content = $template->getContent($channel, $locale);
        
        return [
            'title' => $this->replaceVariables($content['title'] ?? '', $variables),
            'body' => $this->replaceVariables($content['body'] ?? '', $variables),
            'metadata' => $content['metadata'] ?? [],
        ];
    }

    /**
     * Get all available templates
     */
    public function getAvailableTemplates(string $type): array
    {
        $templates = [];

        // Add registered templates
        foreach ($this->registeredTemplates as $id => $template) {
            if ($type === null || $template->getType() === $type) {
                $templates[$id] = [
                    'id' => $id,
                    'name' => $template->getName(),
                    'type' => $template->getType(),
                    'channels' => $template->getSupportedChannels(),
                    'locales' => $template->getSupportedLocales(),
                    'required_variables' => $template->getRequiredVariables(),
                    'optional_variables' => $template->getOptionalVariables(),
                    'source' => 'registered'
                ];
            }
        }

        // Add database templates
        $query = NotificationTemplate::active();
        if ($type) {
            $query->byType($type);
        }

        foreach ($query->get() as $template) {
            $templates[$template->getId()] = [
                'id' => $template->getId(),
                'name' => $template->getName(),
                'type' => $template->getType(),
                'channels' => $template->getSupportedChannels(),
                'locales' => $template->getSupportedLocales(),
                'required_variables' => $template->getRequiredVariables(),
                'optional_variables' => $template->getOptionalVariables(),
                'source' => 'database'
            ];
        }

        return $templates;
    }

    /**
     * Create a new template in database
     */
    public function createTemplate(array $data): NotificationTemplate
    {
        $template = NotificationTemplate::createTemplate($data);
        
        // Clear cache
        Cache::forget("notification_template_{$template->id}");
        
        Log::info("Template created", [
            'template_id' => $template->id,
            'name' => $template->name,
            'type' => $template->type
        ]);

        return $template;
    }

    /**
     * Update template
     */
    public function updateTemplate(string $id, array $data): bool
    {
        try {
            $template = NotificationTemplate::find($id);
            
            if (!$template) {
                throw new Exception("Template not found: {$id}");
            }

            $template->update($data);
            
            // Clear cache
            Cache::forget("notification_template_{$id}");
            
            Log::info("Template updated", [
                'template_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to update template", [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Delete template
     */
    public function deleteTemplate(string $id): bool
    {
        try {
            $template = NotificationTemplate::find($id);
            
            if (!$template) {
                throw new Exception("Template not found: {$id}");
            }

            $template->delete();
            
            // Clear cache
            Cache::forget("notification_template_{$id}");
            
            Log::info("Template deleted", ['template_id' => $id]);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete template", [
                'template_id' => $id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Validate template variables
     */
    public function validateTemplateVariables(string $templateId, array $variables): array
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template) {
            return [
                'valid' => false,
                'errors' => ["Template not found: {$templateId}"]
            ];
        }

        $errors = [];
        $required = $template->getRequiredVariables();
        
        foreach ($required as $variable) {
            if (!isset($variables[$variable])) {
                $errors[] = "Missing required variable: {$variable}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'required_variables' => $required,
            'optional_variables' => $template->getOptionalVariables(),
            'provided_variables' => array_keys($variables)
        ];
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(string $templateId, int $days = 30): array
    {
        // This would query notification logs for usage statistics
        return [
            'template_id' => $templateId,
            'period_days' => $days,
            'total_uses' => 0,
            'success_rate' => 0,
            'channels_used' => [],
            'most_used_locale' => 'ar',
        ];
    }



    /**
     * Load default templates from configuration
     */
    public function loadDefaultTemplates(): void
    {
        $defaultTemplates = config('notifications.default_templates', []);
        
        foreach ($defaultTemplates as $id => $templateData) {
            // Create template instance and register it
            // This would be implemented based on your template structure
        }
    }
}
