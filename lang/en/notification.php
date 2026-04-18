<?php
return [
    'view_channel' => 'View Channel',
    'regards' => 'Best Regards',
    'greeting' => 'Hello!',
    'thank_you' => 'Thank you for using our services.',
    'signature' => 'The Dreams Team',
    'contact_support' => 'Please contact our support team for assistance.',
    'seamless_communication'=>'Give customers a seamless communication',
    'terms_of_use' => 'Terms of use',
    'privacy_policy' => 'Privacy Policy',
    'sms' => [
        'channel' => [
            'expiry' => [
                'alert' => "SMS channel ':channel' will expire in :days days. Please take necessary actions to prevent service interruption.",
            ],
            'disabled' => [
                'alert' => "SMS sender for :channel has been disabled (expired on :date). Contact support for renewal.",
            ],
            'status' => [
                'approved' => 'SMS channel ":channel_name" for ":org_name" has been approved and is now active.',
                'rejected' => 'SMS channel ":channel_name" for ":org_name" has been rejected. Please contact support for more information.',
                'payment_required' => 'Payment required for SMS channel ":channel_name" in ":org_name". Please login to complete the payment.'
            ]
        ],
        'statistics' => [
            'processing' => [
                'success' => 'Processing completed! Numbers: :count, Cost: :cost Points.',
                'failure' => 'Processing failed: :error.',
                'insufficient_balance' => 'Processing failed due to insufficient balance: :error.',
                'auto_approval_notice' => 'Auto-approved in 10 min if no action taken.'
            ]
        ]
    ],
    'email' => [
        'channel' => [
            'expiry' => [
                'subject' => 'Channel Expiry Notice - :channel',
                'title' => 'Channel Expiry Notice',
                'channel_expiry' => 'Your channel ":channel" in workspace ":workspace" will expire in :days days.',
                'expiration_date' => 'Expiration Date: :date',
                'platform' => 'Platform: :platform',
                'action_needed' => 'Please renew your subscription to avoid service interruption.',
            ],
            'disabled' => [
                'subject' => 'SMS Sender Disabled - :channel',
                'title' => 'Channel Disabled - :channel',
                'channel_disabled' => 'Your channel ":channel" has been disabled.',
                'expiration_date' => 'Expiration Date: :date',
            ],
            'status' => [
                'approved' => [
                    'subject' => 'SMS Channel Approved',
                    'title' => 'Channel Approved',
                    'greeting' => 'Hello :name',
                    'line1' => 'Great news! The SMS channel ":channel_name" for organization ":org_name" has been approved.',
                    'line2' => 'You can now start using this channel for your SMS communications.',
                    'action' => 'Go to Dashboard',
                    'thanks' => 'Thank you for using our services!'
                ],
                'rejected' => [
                    'subject' => 'SMS Channel Rejected',
                    'title' => 'Channel Rejected',
                    'greeting' => 'Hello :name',
                    'line1' => 'We regret to inform you that the SMS channel ":channel_name" for organization ":org_name" has been rejected.',
                    'line2' => 'Please contact support for more information about why your channel was rejected and how to resolve any issues.',
                    'action' => 'Go to Dashboard',
                    'thanks' => 'Thank you for using our services!'
                ],
                'payment_required' => [
                    'subject' => 'Payment Required for SMS Channel',
                    'title' => 'Payment Required',
                    'greeting' => 'Hello :name',
                    'line1' => 'The SMS channel ":channel_name" for organization ":org_name" requires payment.',
                    'line2' => 'Please complete the payment to activate the channel.',
                    'action' => 'Go to Dashboard',
                    'thanks' => 'Thank you for using our services!'
                ],
            ]
        ],
        'statistics' => [
            'processing' => [
                'completed' => [
                    'subject' => 'SMS Statistics Processing Completed',
                    'greeting' => 'Hello :name!',
                    'success_message' => 'Your SMS statistics processing has been completed successfully.',
                    'processing_id' => 'Processing ID: :id',
                    'total_numbers' => 'Total Numbers: :count',
                    'total_cost' => 'Total Cost: :cost SAR',
                    'review_message' => 'Please review and approve the results to proceed with sending. Auto-approved in 10 min if no action taken.',
                    'action_button' => 'Review Results',
                    'thank_you' => 'Thank you for using our SMS service!'
                ],
                'failed' => [
                    'subject' => 'SMS Statistics Processing Failed',
                    'greeting' => 'Hello :name!',
                    'failure_message' => 'Your SMS statistics processing has failed.',
                    'processing_id' => 'Processing ID: :id',
                    'error_message' => 'Error: :error',
                    'retry_message' => 'Please try again or contact support if the issue persists.',
                    'thank_you' => 'Thank you for using our SMS service!'
                ]
            ]
        ],
        'management' => [
            'general' => [
                'subject' => ':subject',
                'title' => 'Management Notification',
            ],
            'payment' => [
                'subject' => ':subject',
                'title' => 'Payment Notification',
                'details_heading' => 'Payment Details',
                'organization' => 'Organization',
                'channel' => 'Channel',
                'platform' => 'Platform',
                'amount' => 'Amount',
                'date' => 'Date',
                'processed' => "A payment of SAR :amount has been made for the activation fee of Sender Name \":channelName\" by organization \":organizationName\".",
            ],
            'channel_status' => [
                'subject' => ':subject',
                'title' => 'Channel Status Update',
                'details_heading' => 'Status Change Details',
                'organization' => 'Organization',
                'channel' => 'Channel',
                'platform' => 'Platform',
                'old_status' => 'Previous Status',
                'new_status' => 'New Status',
                'date' => 'Date',
            ],
            'new_channel' => [
                'subject' => ':subject',
                'title' => 'New Channel Notification',
                'details_heading' => 'Channel Details',
                'organization' => 'Organization',
                'channel' => 'Channel',
                'platform' => 'Platform',
                'status' => 'Status',
                'date' => 'Date',
            ],
            'channel_deletion' => [
                'subject' => ':subject',
                'title' => 'Channel Deletion Notification',
                'details_heading' => 'Deletion Details',
                'organization' => 'Organization',
                'channel' => 'Channel',
                'platform' => 'Platform',
                'date' => 'Date',
            ],
            'sender_request' => [
                'subject' => ':subject',
                'title' => 'New Sender Name Request',
                'details_heading' => 'Sender Request Details',
                'organization' => 'Organization',
                'sender_name' => 'Requested Sender Name',
                'date' => 'Date',
            ],
        ],
        'ticket' => [
            'subject-email-created' => "Ticket Created: :subject [Ticket: :ticket_number]",
            'subject-email-replay' => "New Reply: :subject [Ticket: :ticket_number]",
            'number' => 'Ticket Number',
            'subject' => 'Ticket Subject',
            'status' => 'Ticket Status',
            'priority' => 'Ticket Priority',
            'created' => 'Creation Date',
            'support_team' => 'Support Team',
            'no-content' => 'No content available',
            'detasils' => 'A new support ticket has been created based on your request. The support team will review your ticket and respond as soon as possible.',
            'attachments' => 'Attachments',
            'has_replied' => 'has replied to your support ticket',
            'new_ticket_created' => 'New Support Ticket Created',
            'new_reply' => 'New Reply to Your Support Ticket',
            'view' => 'View Ticket',
        ],
    ],
];