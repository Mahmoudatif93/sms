@extends('emails.email-template')
@section('styles')
    <style>
        .ticket-info {
            background-color: #f5f7fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .ticket-info p {
            margin: 5px 0;
        }

        .ticket-info strong {
            color: #2c3e50;
        }

        .ticket-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-open {
            background-color: #3498db;
            color: white;
        }

        .status-pending {
            background-color: #f39c12;
            color: white;
        }

        .status-resolved {
            background-color: #2ecc71;
            color: white;
        }

        .status-closed {
            background-color: #95a5a6;
            color: white;
        }

        .message-content {
            background-color: white;
            border: 1px solid #e1e1e1;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
@endsection
@section('content')
    <div class="email-container">
        <div class="email-body">
            <div class="ticket-info">
                <p><strong>{{ __('notification.email.ticket.number') }}:</strong> <span class="ref-number">#{{ $ticket->ticket_number }}</span></p>
                <p><strong>{{ __('notification.email.ticket.subject') }}:</strong> {{ $ticket->subject }}</p>
                <p>
                    <strong>{{ __('notification.email.ticket.status') }}:</strong>
                    <span class="ticket-status status-{{ strtolower($ticket->status) }}">
                        {{ $ticket->status }}
                    </span>
                </p>
                <p>
                    <strong>{{ __('notification.email.ticket.priority') }}:</strong>
                    <span class="priority-{{ strtolower($ticket->priority) }}">
                        {{ $ticket->priority }}
                    </span>
                </p>
                <p><strong>{{ __('notification.email.ticket.created') }}:</strong> {{ \Carbon\Carbon::parse($ticket->created_at)->format('F j, Y, g:i a') }}</p>
            </div>

            @if($isNewTicket)
                <p>{{ __('notification.email.ticket.detasils') }}</p>
            @else
                <p>{{ $message->sender->name ??  __('notification.email.ticket.support_team') }} {{ __('notification.email.ticket.has_replied') }}:</p>
            @endif

            <div class="message-content">
                <div class="message-metadata">
                    <strong>{{ $message->sender->name ??  __('notification.email.ticket.support_team')}}</strong> •
                    {{ \Carbon\Carbon::parse($message->created_at)->format('F j, Y, g:i a') }}
                </div>
                <div class="message-body">
                    {!! $message->messageable->content ?? $message->content ?? __('notification.email.ticket.no-content') !!}
                </div>

                @if(isset($message->attachments) && count($message->attachments) > 0)
                    <div class="attachments">
                        <p><strong>{{ __('notification.email.ticket.attachments') }}:</strong></p>
                        @foreach($message->attachments as $attachment)
                            <div class="attachment">
                                <span>{{ $attachment->file_name }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

<!-- 
            <p>Please do not reply directly to this email. To respond to this ticket, please click the button above or reply
                from the customer portal.</p> -->

        </div>
    </div>
@endsection