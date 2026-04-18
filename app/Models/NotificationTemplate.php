<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Contracts\NotificationTemplateInterface;
use App\Notifications\Core\NotificationMessage;

class NotificationTemplate extends Model implements NotificationTemplateInterface
{

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'type',
        'description',
        'content',
        'supported_channels',
        'supported_locales',
        'required_variables',
        'optional_variables',
        'is_active',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'supported_channels' => 'array',
        'supported_locales' => 'array',
        'required_variables' => 'array',
        'optional_variables' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get notification logs that used this template
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by channel support
     */
    public function scopeSupportsChannel($query, string $channel)
    {
        return $query->whereJsonContains('channels', $channel);
    }

    /**
     * Scope by locale support
     */
    public function scopeSupportsLocale($query, string $locale)
    {
        return $query->whereJsonContains('locales', $locale);
    }

    /**
     * Get template ID
     */
    public function getId(): string
    {
        return (string) $this->id;
    }

    /**
     * Get template name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get template type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get template content for a specific channel
     */
    public function getContent(string $channel, string $locale = 'ar'): array
    {
        $content = $this->content[$locale][$channel] ?? $this->content['ar'][$channel] ?? [];

        if (empty($content) && isset($this->content[$locale]['default'])) {
            $content = $this->content[$locale]['default'] ?? $this->content['ar']['default'] ?? [];
        }

        return $content;
    }

    /**
     * Get required variables for this template
     */
    public function getRequiredVariables(): array
    {
        return $this->required_variables ?? [];
    }

    /**
     * Get optional variables for this template
     */
    public function getOptionalVariables(): array
    {
        return $this->optional_variables ?? [];
    }

    /**
     * Validate variables against template requirements
     */
    public function validateVariables(array $variables): bool
    {
        $required = $this->getRequiredVariables();

        foreach ($required as $variable) {
            if (!isset($variables[$variable])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render template with variables
     */
    public function render(array $variables, string $channel = 'sms2', string $locale = 'ar'): NotificationMessage
    {
        if (!$this->validateVariables($variables)) {
            throw new \InvalidArgumentException('Missing required variables for template');
        }

        $content = $this->getContent($channel, $locale);
        $title = $this->replaceVariables($content['title'] ?? '', $variables);
        $body = $this->replaceVariables($content['body'] ?? '', $variables);

        $message = new NotificationMessage($this->type, $body);
        $message->setTitle($title)
               ->setTemplateId($this->getId())
               ->setTemplateVariables($variables)
               ->setLocale($locale)
               ->addChannel($channel);

        return $message;
    }

    /**
     * Replace variables in content
     */
    protected function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $value, $content);
        }

        return $content;
    }

    /**
     * Get supported channels for this template
     */
    public function getSupportedChannels(): array
    {
        return $this->supported_channels ?? [];
    }

    /**
     * Get supported locales for this template
     */
    public function getSupportedLocales(): array
    {
        return $this->supported_locales ?? ['ar', 'en'];
    }

    /**
     * Check if template supports a specific channel
     */
    public function supportsChannel(string $channel): bool
    {
        return in_array($channel, $this->getSupportedChannels());
    }

    /**
     * Check if template supports a specific locale
     */
    public function supportsLocale(string $locale): bool
    {
        return in_array($locale, $this->getSupportedLocales());
    }

    /**
     * Get template metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * Get template configuration
     */
    public function getConfiguration(): array
    {
        return $this->configuration ?? [];
    }

    /**
     * Create a new template
     */
    public static function createTemplate(array $data): self
    {
        return self::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? '',
            'content' => $data['content'],
            'supported_channels' => $data['supported_channels'] ?? ['sms', 'email'],
            'supported_locales' => $data['supported_locales'] ?? ['ar', 'en'],
            'required_variables' => $data['required_variables'] ?? [],
            'optional_variables' => $data['optional_variables'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? [],
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Update template content
     */
    public function updateContent(array $content): bool
    {
        return $this->update(['content' => $content]);
    }

    /**
     * Add variable to template
     */
    public function addVariable(string $name, bool $required = false): void
    {
        if ($required) {
            $requiredVars = $this->required_variables ?? [];
            if (!in_array($name, $requiredVars)) {
                $requiredVars[] = $name;
                $this->update(['required_variables' => $requiredVars]);
            }
        } else {
            $optionalVars = $this->optional_variables ?? [];
            if (!in_array($name, $optionalVars)) {
                $optionalVars[] = $name;
                $this->update(['optional_variables' => $optionalVars]);
            }
        }
    }

    /**
     * Remove variable from template
     */
    public function removeVariable(string $name): void
    {
        $requiredVars = $this->required_variables ?? [];
        $optionalVars = $this->optional_variables ?? [];

        $requiredVars = array_values(array_filter($requiredVars, fn($v) => $v !== $name));
        $optionalVars = array_values(array_filter($optionalVars, fn($v) => $v !== $name));

        $this->update([
            'required_variables' => $requiredVars,
            'optional_variables' => $optionalVars
        ]);
    }

    /**
     * Clone template
     */
    public function cloneTemplate(string $newName): self
    {
        $data = $this->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);
        $data['name'] = $newName;

        return self::create($data);
    }
}
