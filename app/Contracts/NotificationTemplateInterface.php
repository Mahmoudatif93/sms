<?php

namespace App\Contracts;

use App\Notifications\Core\NotificationMessage;

interface NotificationTemplateInterface
{
    /**
     * Get template ID
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get template name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get template type
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get template content for a specific channel
     *
     * @param string $channel
     * @param string $locale
     * @return array
     */
    public function getContent(string $channel, string $locale = 'ar'): array;

    /**
     * Get required variables for this template
     *
     * @return array
     */
    public function getRequiredVariables(): array;

    /**
     * Get optional variables for this template
     *
     * @return array
     */
    public function getOptionalVariables(): array;

    /**
     * Validate variables against template requirements
     *
     * @param array $variables
     * @return bool
     */
    public function validateVariables(array $variables): bool;

    /**
     * Render template with variables
     *
     * @param array $variables
     * @param string $channel
     * @param string $locale
     * @return NotificationMessage
     */
    public function render(array $variables, string $channel = 'sms', string $locale = 'ar'): NotificationMessage;

    /**
     * Get supported channels for this template
     *
     * @return array
     */
    public function getSupportedChannels(): array;

    /**
     * Get supported locales for this template
     *
     * @return array
     */
    public function getSupportedLocales(): array;

    /**
     * Check if template supports a specific channel
     *
     * @param string $channel
     * @return bool
     */
    public function supportsChannel(string $channel): bool;

    /**
     * Check if template supports a specific locale
     *
     * @param string $locale
     * @return bool
     */
    public function supportsLocale(string $locale): bool;

    /**
     * Get template metadata
     *
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Get template configuration
     *
     * @return array
     */
    public function getConfiguration(): array;
}
