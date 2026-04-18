@extends('emails.email-template')
@section('content')
@if($type === 'payment')
        <div class="details">
            <h3>{{ __('notification.email.management.payment.details_heading') }}</h3>
            <ul>
                <li>{{ __('notification.email.management.payment.organization') }}: {{ $data['organization_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.payment.channel') }}: {{ $data['channel_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.payment.platform') }}: {{ $data['platform'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.payment.amount') }}: SAR {{ $data['amount'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.payment.date') }}: {{ date('Y-m-d H:i:s', strtotime($data['date'] ?? now())) }}</li>
            </ul>
        </div>
    @endif
    
    @if($type === 'channel_status')
        <div class="details">
            <h3>{{ __('notification.email.management.channel_status.details_heading') }}</h3>
            <ul>
                <li>{{ __('notification.email.management.channel_status.organization') }}: {{ $data['organization_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_status.channel') }}: {{ $data['channel_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_status.platform') }}: {{ $data['platform'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_status.old_status') }}: {{ $data['old_status'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_status.new_status') }}: {{ $data['new_status'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_status.date') }}: {{ date('Y-m-d H:i:s', strtotime($data['date'] ?? now())) }}</li>
            </ul>
        </div>
    @endif
    
    @if($type === 'new_channel')
        <div class="details">
            <h3>{{ __('notification.email.management.new_channel.details_heading') }}</h3>
            <ul>
                <li>{{ __('notification.email.management.new_channel.organization') }}: {{ $data['organization_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.new_channel.channel') }}: {{ $data['channel_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.new_channel.platform') }}: {{ $data['platform'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.new_channel.status') }}: {{ $data['status'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.new_channel.date') }}: {{ date('Y-m-d H:i:s', strtotime($data['date'] ?? now())) }}</li>
            </ul>
        </div>
    @endif
    
    @if($type === 'channel_deletion')
        <div class="details">
            <h3>{{ __('notification.email.management.channel_deletion.details_heading') }}</h3>
            <ul>
                <li>{{ __('notification.email.management.channel_deletion.organization') }}: {{ $data['organization_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_deletion.channel') }}: {{ $data['channel_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_deletion.platform') }}: {{ $data['platform'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.channel_deletion.date') }}: {{ date('Y-m-d H:i:s', strtotime($data['date'] ?? now())) }}</li>
            </ul>
        </div>
    @endif
    
    @if($type === 'sender_request')
        <div class="details">
            <h3>{{ __('notification.email.management.sender_request.details_heading') }}</h3>
            <ul>
                <li>{{ __('notification.email.management.sender_request.organization') }}: {{ $data['organization_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.sender_request.sender_name') }}: {{ $data['sender_name'] ?? 'N/A' }}</li>
                <li>{{ __('notification.email.management.sender_request.date') }}: {{ date('Y-m-d H:i:s', strtotime($data['date'] ?? now())) }}</li>
            </ul>
        </div>
    @endif
@endsection