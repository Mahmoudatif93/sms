<?php

namespace App\Domain\Conversation\Services;

use App\Domain\Conversation\Channels\ChannelInterface;
use App\Domain\Conversation\Channels\InstagramChannel;
use App\Domain\Conversation\Channels\LiveChatChannel;
use App\Domain\Conversation\Channels\MessengerChannel;
use App\Domain\Conversation\Channels\TelegramChannel;
use App\Domain\Conversation\Channels\WhatsAppChannel;
use App\Models\Channel;
use InvalidArgumentException;

class ChannelResolver
{
    private array $channels = [];

    public function __construct(
        WhatsAppChannel $whatsapp,
        LiveChatChannel $livechat,
        MessengerChannel $messenger,
        TelegramChannel $telegram,
        InstagramChannel $instagram
    ) {
        $this->channels = [
            Channel::WHATSAPP_PLATFORM => $whatsapp,
            Channel::LIVECHAT_PLATFORM => $livechat,
            Channel::MESSENGER_PLATFORM => $messenger,
            TelegramChannel::PLATFORM => $telegram,
            InstagramChannel::PLATFORM => $instagram,
        ];
    }

    /**
     * Resolve channel implementation by platform name
     *
     * @param string $platform
     * @return ChannelInterface
     * @throws InvalidArgumentException
     */
    public function resolve(string $platform): ChannelInterface
    {
        if (!$this->supports($platform)) {
            throw new InvalidArgumentException("Unsupported platform: {$platform}");
        }

        return $this->channels[$platform];
    }

    /**
     * Check if platform is supported
     *
     * @param string $platform
     * @return bool
     */
    public function supports(string $platform): bool
    {
        return isset($this->channels[$platform]);
    }

    /**
     * Get all supported platforms
     *
     * @return array
     */
    public function getSupportedPlatforms(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Get all registered channels
     *
     * @return array<string, ChannelInterface>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Register a new channel
     *
     * @param string $platform
     * @param ChannelInterface $channel
     * @return void
     */
    public function register(string $platform, ChannelInterface $channel): void
    {
        $this->channels[$platform] = $channel;
    }
}
