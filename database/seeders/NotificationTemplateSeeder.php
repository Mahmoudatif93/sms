<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // OTP Login Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'login_otp'],
            [
                'name' => 'Login OTP Template',
                'type' => 'login',
                'supported_channels' => ['sms', 'email'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['otp_code'],
                'optional_variables' => ['user_name', 'ip_address', 'timestamp'],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'رمز التحقق',
                            'body' => 'رمز التحقق الخاص بك: {otp_code}.'
                        ],
                        'email' => [
                            'title' => 'رمز التحقق - Dreams SMS',
                            'body' => '{otp_code}',
                            'template' => 'mail.login_otp_ar'
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'Verification Code',
                            'body' => 'Your verification code: {otp_code}.'
                        ],
                        'email' => [
                            'title' => 'Verification Code - Dreams',
                            'body' => '{otp_code}',
                            'template' => 'mail.login_otp_en'
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'high',
                    'expires_in' => 600,
                    'category' => 'authentication'
                ]
            ]
        );

        // Welcome Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'welcome_user'],
            [
                'name' => 'Welcome User Template',
                'type' => 'welcome',
                'supported_channels' => ['sms', 'email'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['user_name'],
                'optional_variables' => ['workspace_name', 'organization_name'],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'مرحباً بك',
                            'body' => 'مرحباً {user_name}، أهلاً بك في Dreams SMS!'
                        ],
                        'email' => [
                            'title' => 'مرحباً بك في Dreams SMS',
                            'body' => 'مرحباً {user_name},\n\nأهلاً بك في Dreams SMS! نحن سعداء لانضمامك إلينا.\n\nيمكنك الآن البدء في استخدام خدماتنا.\n\nشكراً لك،\nفريق Dreams SMS'
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'Welcome',
                            'body' => 'Welcome {user_name}, to Dreams SMS!'
                        ],
                        'email' => [
                            'title' => 'Welcome to Dreams SMS',
                            'body' => 'Hello {user_name},\n\nWelcome to Dreams SMS! We are happy to have you join us.\n\nYou can now start using our services.\n\nThank you,\nDreams SMS Team'
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'normal',
                    'category' => 'onboarding'
                ]
            ]
        );

        // Admin Alert Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'admin_alert'],
            [
                'name' => 'Admin Alert Template',
                'type' => 'admin',
                'supported_channels' => ['sms', 'email', 'telegram'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['alert_message'],
                'optional_variables' => ['user_name', 'workspace_name', 'severity'],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'تنبيه إداري',
                            'body' => 'تنبيه: {alert_message}'
                        ],
                        'email' => [
                            'title' => 'تنبيه إداري - Dreams SMS',
                            'body' => 'تنبيه إداري:\n\n{alert_message}\n\nالوقت: {timestamp}\nالمستخدم: {user_name}\nمساحة العمل: {workspace_name}'
                        ],
                        'telegram' => [
                            'title' => '🚨 تنبيه إداري',
                            'body' => '🚨 *تنبيه إداري*\n\n{alert_message}\n\n⏰ الوقت: {timestamp}\n👤 المستخدم: {user_name}\n🏢 مساحة العمل: {workspace_name}'
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'Admin Alert',
                            'body' => 'Alert: {alert_message}'
                        ],
                        'email' => [
                            'title' => 'Admin Alert - Dreams SMS',
                            'body' => 'Admin Alert:\n\n{alert_message}\n\nTime: {timestamp}\nUser: {user_name}\nWorkspace: {workspace_name}'
                        ],
                        'telegram' => [
                            'title' => '🚨 Admin Alert',
                            'body' => '🚨 *Admin Alert*\n\n{alert_message}\n\n⏰ Time: {timestamp}\n👤 User: {user_name}\n🏢 Workspace: {workspace_name}'
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'urgent',
                    'category' => 'administration'
                ]
            ]
        );

        // Registration OTP Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'registration_otp'],
            [
                'name' => 'Registration OTP Template',
                'type' => 'registration',
                'supported_channels' => ['sms', 'email'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['otp_code', 'user_name'],
                'optional_variables' => ['site_name', 'username', 'number'],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'رمز التفعيل',
                            'body' => 'مرحباً {user_name}، رمز التفعيل الخاص بك: {otp_code}. أدخل هذا الرمز لإكمال التسجيل.'
                        ],
                        'email' => [
                            'title' => 'رمز التفعيل - Dreams SMS',
                            'body' => 'مرحباً {user_name},\n\nشكراً لتسجيلك في Dreams SMS!\n\nرمز التفعيل الخاص بك: {otp_code}\n\nيرجى إدخال هذا الرمز لإكمال عملية التسجيل.\n\nمرحباً بك في عائلة Dreams SMS!\n\nفريق Dreams SMS',
                            'template' => 'mail.registration_otp_ar'
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'Activation Code',
                            'body' => 'Hello {user_name}, your activation code: {otp_code}. Enter this code to complete registration.'
                        ],
                        'email' => [
                            'title' => 'Activation Code - Dreams SMS',
                            'body' => 'Hello {user_name},\n\nThank you for registering with Dreams SMS!\n\nYour activation code: {otp_code}\n\nPlease enter this code to complete your registration.\n\nWelcome to the Dreams SMS family!\n\nDreams SMS Team',
                            'template' => 'mail.registration_otp_en'
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'high',
                    'expires_in' => 300,
                    'category' => 'registration'
                ]
            ]
        );

        // Welcome Message Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'welcome_message'],
            [
                'name' => 'Welcome Message Template',
                'type' => 'welcome',
                'supported_channels' => ['sms', 'email'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['user_name', 'site_name'],
                'optional_variables' => [],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'مرحباً بك',
                            'body' => 'أهلاً وسهلاً {user_name}! مرحباً بك في {site_name}. نحن سعداء بانضمامك إلينا!'
                        ],
                        'email' => [
                            'title' => 'مرحباً بك في {site_name}',
                            'subject' => 'مرحباً بك في {site_name}',
                            'template' => 'mail.notifications.welcome_ar'
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'Welcome',
                            'body' => 'Welcome {user_name}! Thank you for joining {site_name}. We\'re excited to have you!'
                        ],
                        'email' => [
                            'title' => 'Welcome to {site_name}',
                            'subject' => 'Welcome to {site_name}',
                            'template' => 'mail.notifications.welcome_en'
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'medium',
                    'expires_in' => null,
                    'category' => 'welcome'
                ]
            ]
        );

        // Message Review Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'message_review'],
            [
                'name' => 'Message Review Template',
                'type' => 'review',
                'supported_channels' => ['telegram'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['message_content'],
                'optional_variables' => ['sender_name', 'message_id', 'review_time'],
                'content' => [
                    'ar' => [
                        'telegram' => [
                            'title' => 'رسالة تحتاج مراجعة',
                            'body' => "🔍 <b>رسالة تحتاج مراجعة</b>\n\n📝 <b>المحتوى:</b>\n{message_content}\n\n👤 <b>المرسل:</b> {sender_name}\n🆔 <b>معرف الرسالة:</b> {message_id}\n⏰ <b>الوقت:</b> {review_time}"
                        ]
                    ],
                    'en' => [
                        'telegram' => [
                            'title' => 'Message Needs Review',
                            'body' => "🔍 <b>Message Needs Review</b>\n\n📝 <b>Content:</b>\n{message_content}\n\n👤 <b>Sender:</b> {sender_name}\n🆔 <b>Message ID:</b> {message_id}\n⏰ <b>Time:</b> {review_time}"
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'high',
                    'expires_in' => null,
                    'category' => 'review'
                ]
            ]
        );

        // New User Admin Notification Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'new_user_admin_notification'],
            [
                'name' => 'New User Admin Notification Template',
                'type' => 'admin_notification',
                'supported_channels' => ['sms', 'email'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['user_name', 'username'],
                'optional_variables' => ['site_name', 'number', 'email', 'registration_time'],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'مستخدم جديد',
                            'body' => 'مستخدم جديد: {user_name} ({username}) انضم إلى {site_name}'
                        ],
                        'email' => [
                            'title' => 'مستخدم جديد - Dreams SMS',
                            'body' => 'مستخدم جديد انضم إلى النظام:\n\nالاسم: {user_name}\nاسم المستخدم: {username}\nرقم الهاتف: {number}\nالبريد الإلكتروني: {email}\nوقت التسجيل: {registration_time}\n\nفريق Dreams SMS',
                            'template' => 'mail.notifications.admin_notification_ar'
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'New User',
                            'body' => 'New user: {user_name} ({username}) joined {site_name}'
                        ],
                        'email' => [
                            'title' => 'New User - Dreams SMS',
                            'body' => 'A new user has joined the system:\n\nName: {user_name}\nUsername: {username}\nPhone: {number}\nEmail: {email}\nRegistration Time: {registration_time}\n\nDreams SMS Team',
                            'template' => 'mail.notifications.admin_notification_en'
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'normal',
                    'category' => 'administration'
                ]
            ]
        );

        // Statistics Notification Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'statistics_notification'],
            [
                'name' => 'Statistics Notification Template',
                'type' => 'statistics',
                'supported_channels' => ['sms', 'email'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['statistics_type', 'status'],
                'optional_variables' => ['user_name', 'total_messages', 'success_count', 'failed_count'],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'إشعار الإحصائيات',
                            'body' => 'إحصائيات {statistics_type}: {status}. إجمالي الرسائل: {total_messages}'
                        ],
                        'email' => [
                            'title' => 'تقرير الإحصائيات - Dreams SMS',
                            'body' => 'مرحباً {user_name},\n\nتقرير إحصائيات {statistics_type}:\n\nالحالة: {status}\nإجمالي الرسائل: {total_messages}\nالرسائل المرسلة بنجاح: {success_count}\nالرسائل الفاشلة: {failed_count}\n\nشكراً لك،\nفريق Dreams SMS'
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'Statistics Notification',
                            'body' => 'Statistics {statistics_type}: {status}. Total messages: {total_messages}'
                        ],
                        'email' => [
                            'title' => 'Statistics Report - Dreams SMS',
                            'body' => 'Hello {user_name},\n\nStatistics report for {statistics_type}:\n\nStatus: {status}\nTotal messages: {total_messages}\nSuccessful messages: {success_count}\nFailed messages: {failed_count}\n\nThank you,\nDreams SMS Team'
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'normal',
                    'category' => 'reporting'
                ]
            ]
        );

        // Message Approval Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'message_approval'],
            [
                'name' => 'Message Approval Template',
                'type' => 'approval',
                'supported_channels' => ['telegram'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['user_name', 'admin_name', 'message_content'],
                'optional_variables' => ['sender_name', 'message_count', 'message_cost', 'message_id', 'approval_time'],
                'content' => [
                    'ar' => [
                        'telegram' => [
                            'title' => 'تمت الموافقة على رسالة',
                            'body' => "✅ <b>تمت الموافقة على رسالة</b>\n\n👤 <b>المستخدم:</b> {user_name}\n👨‍💼 <b>تمت الموافقة بواسطة:</b> {admin_name}\n📝 <b>محتوى الرسالة:</b>\n{message_content}\n\n📊 <b>تفاصيل الرسالة:</b>\n📱 <b>المرسل:</b> {sender_name}\n🔢 <b>عدد الرسائل:</b> {message_count}\n💰 <b>التكلفة:</b> {message_cost} نقطة\n🆔 <b>معرف الرسالة:</b> {message_id}\n⏰ <b>وقت الموافقة:</b> {approval_time}"
                        ]
                    ],
                    'en' => [
                        'telegram' => [
                            'title' => 'Message Approved',
                            'body' => "✅ <b>Message Approved</b>\n\n👤 <b>User:</b> {user_name}\n👨‍💼 <b>Approved by:</b> {admin_name}\n📝 <b>Message Content:</b>\n{message_content}\n\n📊 <b>Message Details:</b>\n📱 <b>Sender:</b> {sender_name}\n🔢 <b>Message Count:</b> {message_count}\n💰 <b>Cost:</b> {message_cost} points\n🆔 <b>Message ID:</b> {message_id}\n⏰ <b>Approval Time:</b> {approval_time}"
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'high',
                    'expires_in' => null,
                    'category' => 'approval'
                ]
            ]
        );

        // Live Chat New Conversation Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'livechat_new_conversation'],
            [
                'name' => 'Live Chat New Conversation Template',
                'type' => 'livechat',
                'supported_channels' => ['telegram'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['conversation_id'],
                'optional_variables' => ['contact_name', 'contact_email', 'contact_phone', 'channel_name', 'timestamp'],
                'content' => [
                    'ar' => [
                        'telegram' => [
                            'title' => 'محادثة جديدة',
                            'body' => "💬 <b>محادثة جديدة في الدردشة المباشرة</b>\n\n👤 <b>الاسم:</b> {contact_name}\n📧 <b>البريد:</b> {contact_email}\n📱 <b>الهاتف:</b> {contact_phone}\n🔗 <b>القناة:</b> {channel_name}\n🆔 <b>رقم المحادثة:</b> {conversation_id}\n⏰ <b>الوقت:</b> {timestamp}"
                        ]
                    ],
                    'en' => [
                        'telegram' => [
                            'title' => 'New Conversation',
                            'body' => "💬 <b>New Live Chat Conversation</b>\n\n👤 <b>Name:</b> {contact_name}\n📧 <b>Email:</b> {contact_email}\n📱 <b>Phone:</b> {contact_phone}\n🔗 <b>Channel:</b> {channel_name}\n🆔 <b>Conversation ID:</b> {conversation_id}\n⏰ <b>Time:</b> {timestamp}"
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'high',
                    'expires_in' => null,
                    'category' => 'livechat'
                ]
            ]
        );

        // Statistics Processing Success Template
        NotificationTemplate::updateOrCreate(
            ['id' => 'statistics_processing_success'],
            [
                'name' => 'Statistics Processing Success Template',
                'type' => 'statistics',
                'supported_channels' => ['sms', 'email', 'telegram'],
                'supported_locales' => ['ar', 'en'],
                'required_variables' => ['user_name', 'total_count', 'total_cost'],
                'optional_variables' => ['processing_time', 'workspace_name'],
                'content' => [
                    'ar' => [
                        'sms' => [
                            'title' => 'اكتملت معالجة الإحصائيات',
                            'body' => 'مرحباً {user_name}، تمت معالجة {total_count} رسالة بتكلفة {total_cost} نقطة بنجاح.'
                        ],
                        'email' => [
                            'title' => 'اكتملت معالجة الإحصائيات - Dreams SMS',
                            'body' => 'مرحباً {user_name},\n\nتمت معالجة الإحصائيات بنجاح!\n\nالتفاصيل:\n- إجمالي الرسائل: {total_count}\n- التكلفة الإجمالية: {total_cost} نقطة\n- وقت المعالجة: {processing_time}\n- مساحة العمل: {workspace_name}\n\nشكراً لك،\nفريق Dreams SMS'
                        ],
                        'telegram' => [
                            'title' => 'اكتملت معالجة الإحصائيات',
                            'body' => "📊 <b>اكتملت معالجة الإحصائيات</b>\n\n👤 <b>المستخدم:</b> {user_name}\n🔢 <b>إجمالي الرسائل:</b> {total_count}\n💰 <b>التكلفة الإجمالية:</b> {total_cost} نقطة\n⏰ <b>وقت المعالجة:</b> {processing_time}\n🏢 <b>مساحة العمل:</b> {workspace_name}"
                        ]
                    ],
                    'en' => [
                        'sms' => [
                            'title' => 'Statistics Processing Complete',
                            'body' => 'Hello {user_name}, successfully processed {total_count} messages with cost {total_cost} points.'
                        ],
                        'email' => [
                            'title' => 'Statistics Processing Complete - Dreams SMS',
                            'body' => 'Hello {user_name},\n\nStatistics processing completed successfully!\n\nDetails:\n- Total messages: {total_count}\n- Total cost: {total_cost} points\n- Processing time: {processing_time}\n- Workspace: {workspace_name}\n\nThank you,\nDreams SMS Team'
                        ],
                        'telegram' => [
                            'title' => 'Statistics Processing Complete',
                            'body' => "📊 <b>Statistics Processing Complete</b>\n\n👤 <b>User:</b> {user_name}\n🔢 <b>Total Messages:</b> {total_count}\n💰 <b>Total Cost:</b> {total_cost} points\n⏰ <b>Processing Time:</b> {processing_time}\n🏢 <b>Workspace:</b> {workspace_name}"
                        ]
                    ]
                ],
                'metadata' => [
                    'priority' => 'normal',
                    'expires_in' => null,
                    'category' => 'statistics'
                ]
            ]
        );
    }
}
