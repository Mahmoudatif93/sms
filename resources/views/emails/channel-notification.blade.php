<x-mail::message>
# {{ $title }}

<div style="direction: {{ $direction }}; text-align: {{ $alignment }};">
    <p>{{ $greeting }}</p>

    @if(isset($channelInfo))
        <div class="channel-info">
            <p>{{ $channelInfo }}</p>
        </div>
    @endif

    @if(isset($expirationDate))
        <div class="expiration-info">
            <p>{{ $expirationDate }}</p>
        </div>
    @endif

    @if(isset($platformInfo))
        <div class="platform-info">
            <p>{{ $platformInfo }}</p>
        </div>
    @endif

    @if(isset($actionNeeded))
        <div class="action-needed">
            <p>{{ $actionNeeded }}</p>
        </div>
    @endif

    @if(isset($supportInfo))
        <div class="support-info">
            <p>{{ $supportInfo }}</p>
        </div>
    @endif
</div>

<x-mail::button :url="$actionUrl" :color="$actionColor">
{{ $actionText }}
</x-mail::button>

<div style="direction: {{ $direction }}; text-align: {{ $alignment }};">
    <p>{{ $thankYou }}</p>

    <p>{{ $signature }}</p>
</div>
</x-mail::message>