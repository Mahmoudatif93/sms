<?php
return [
    'view_channel' => 'عرض القناة',
    'regards' => 'مع أطيب التحيات',
    'greeting' => 'مرحباً!',
    'thank_you' => 'شكراً لاستخدام خدماتنا.',
    'signature' => 'فريق دريمز',
    'contact_support' => 'يرجى الاتصال بفريق الدعم للحصول على المساعدة.',
    'seamless_communication' => 'تقديم تواصل سلس للعملاء',
    'terms_of_use' => 'شروط الاستخدام',
    'privacy_policy' => 'سياسة الخصوصية',
    'sms' => [
        'channel' => [
            'expiry' => [
                'alert' => "قناة الرسائل القصيرة ':channel' ستنتهي صلاحيتها خلال :days يوم. يرجى اتخاذ الإجراءات اللازمة لمنع انقطاع الخدمة.",
            ],
            'disabled' => [
                'alert' => " تم تعطيل مرسل الرسائل القصيرة لـ :channel (انتهت الصلاحية في :date). اتصل بالدعم للتجديد.",
            ],
            'status' => [
                'approved' => 'تمت الموافقة على قناة الرسائل القصيرة ":channel_name" لـ ":org_name" وهي نشطة الآن.',
                'rejected' => 'تم رفض قناة الرسائل القصيرة ":channel_name" لـ ":org_name". يرجى الاتصال بالدعم للحصول على مزيد من المعلومات.',
                'payment_required' => 'دفع مطلوب لقناة الرسائل القصيرة ":channel_name" في ":org_name". يرجى تسجيل الدخول لإكمال الدفع.'
            ]
        ],
        'statistics' => [
            'processing' => [
                'success' => 'تمت المعالجة بنجاح! الأرقام: :count، التكلفة: :cost نقطة.',
                'failure' => 'فشلت المعالجة: :error.',
                'insufficient_balance' => 'فشلت المعالجة بسبب عدم كفاية الرصيد: :error.',
                'auto_approval_notice' => 'يتم الاعتماد تلقائياً خلال 10 د إذا لم يتم اتخاذ إجراء.'
            ]
        ]
    ],
    'email' => [
        'channel' => [
            'expiry' => [
                'subject' => 'إشعار انتهاء القناة - :channel',
                'title' => 'إشعار انتهاء القناة',
                'channel_expiry' => 'ستنتهي قناتك ":channel" في مساحة العمل ":workspace" خلال :days أيام.',
                'expiration_date' => 'تاريخ الانتهاء: :date',
                'platform' => 'المنصة: :platform',
                'action_needed' => 'يرجى تجديد اشتراكك لتجنب انقطاع الخدمة.',
            ],
            'disabled' => [
                'subject' => 'تم تعطيل مرسل الرسائل القصيرة - :channel',
                'title' => 'تم تعطيل مرسل الرسائل القصيرة - :channel',
                'channel_disabled' => 'تم تعطيل قناتك ":channel".',
                'expiration_date' => 'تاريخ الانتهاء: :date'
            ],
            'status' => [
                'approved' => [
                    'subject' => 'تم الموافقة على قناة الرسائل القصيرة',
                    'title' => 'الموافقة على قناة الرسائل القصيرة',
                    'greeting' => 'مرحباً :name',
                    'line1' => 'أخبار سارة! تمت الموافقة على قناة الرسائل القصيرة ":channel_name" للمؤسسة ":org_name".',
                    'line2' => 'يمكنك الآن البدء في استخدام هذه القناة لارسال الرسائل القصيرة الخاصة بك.',
                    'action' => 'الذهاب إلى لوحة التحكم',
                    'thanks' => 'شكراً لاستخدامك خدماتنا!'
                ],
                'rejected' => [
                    'subject' => 'تم رفض قناة الرسائل القصيرة',
                    'title' => 'رفض قناة الرسائل القصيرة',
                    'greeting' => 'مرحباً :name',
                    'line1' => 'نأسف لإبلاغك بأنه تم رفض قناة الرسائل القصيرة ":channel_name" للمؤسسة ":org_name".',
                    'line2' => 'يرجى الاتصال بالدعم للحصول على مزيد من المعلومات حول سبب رفض قناتك وكيفية حل أي مشكلات.',
                    'action' => 'الذهاب إلى لوحة التحكم',
                    'thanks' => 'شكراً لاستخدامك خدماتنا!'
                ],
                'payment_required' => [
                    'subject' => 'دفع مطلوب لقناة الرسائل القصيرة',
                    'title' => 'دفع مطلوب لقناة الرسائل القصيرة',
                    'greeting' => 'مرحباً :name',
                    'line1' => 'قناة الرسائل القصيرة ":channel_name" للمؤسسة ":org_name" تتطلب الدفع.',
                    'line2' => 'يرجى إكمال الدفع لتفعيل القناة.',
                    'action' => 'الذهاب إلى لوحة التحكم',
                    'thanks' => 'شكراً لاستخدامك خدماتنا!'
                ],

            ]
        ],
        'statistics' => [
            'processing' => [
                'completed' => [
                    'subject' => 'تم إكمال معالجة إحصائيات الرسائل القصيرة',
                    'greeting' => 'مرحباً :name!',
                    'success_message' => 'تم إكمال معالجة إحصائيات الرسائل القصيرة بنجاح.',
                    'processing_id' => 'معرف المعالجة: :id',
                    'total_numbers' => 'إجمالي الأرقام: :count',
                    'total_cost' => 'التكلفة الإجمالية: :cost ريال سعودي',
                    'review_message' => 'يرجى مراجعة النتائج والموافقة عليها للمتابعة مع الإرسال. سيتم الموافقة تلقائياً خلال 10 دقائق إذا لم يتم اتخاذ إجراء.',
                    'action_button' => 'مراجعة النتائج',
                    'thank_you' => 'شكراً لاستخدامك خدمة الرسائل القصيرة!'
                ],
                'failed' => [
                    'subject' => 'فشلت معالجة إحصائيات الرسائل القصيرة',
                    'greeting' => 'مرحباً :name!',
                    'failure_message' => 'فشلت معالجة إحصائيات الرسائل القصيرة.',
                    'processing_id' => 'معرف المعالجة: :id',
                    'error_message' => 'الخطأ: :error',
                    'retry_message' => 'يرجى المحاولة مرة أخرى أو الاتصال بالدعم إذا استمرت المشكلة.',
                    'thank_you' => 'شكراً لاستخدامك خدمة الرسائل القصيرة!'
                ]
            ]
        ],
        'management' => [
            'general' => [
                'subject' => ':subject',
                'title' => 'إشعار للإدارة',
            ],
            'payment' => [
                'subject' => ':subject',
                'title' => 'إشعار الدفع',
                'details_heading' => 'تفاصيل الدفع',
                'organization' => 'المؤسسة',
                'channel' => 'القناة',
                'platform' => 'المنصة',
                'amount' => 'المبلغ',
                'date' => 'التاريخ',
                'processed' => "تم تسديد رسوم تفعيل اسم المرسل \":channelName\" بمبلغ :amount ريال سعودي من قبل المنظمة \":organizationName.\""
            ],
            'channel_status' => [
                'subject' => ':subject',
                'title' => 'تحديث حالة القناة',
                'details_heading' => 'تفاصيل تغيير الحالة',
                'organization' => 'المؤسسة',
                'channel' => 'القناة',
                'platform' => 'المنصة',
                'old_status' => 'الحالة السابقة',
                'new_status' => 'الحالة الجديدة',
                'date' => 'التاريخ',
            ],
            'new_channel' => [
                'subject' => ':subject',
                'title' => 'إشعار قناة جديدة',
                'details_heading' => 'تفاصيل القناة',
                'organization' => 'المؤسسة',
                'channel' => 'القناة',
                'platform' => 'المنصة',
                'status' => 'الحالة',
                'date' => 'التاريخ',
            ],
            'channel_deletion' => [
                'subject' => ':subject',
                'title' => 'إشعار حذف القناة',
                'details_heading' => 'تفاصيل الحذف',
                'organization' => 'المؤسسة',
                'channel' => 'القناة',
                'platform' => 'المنصة',
                'date' => 'التاريخ',
            ],
            'sender_request' => [
                'subject' => ':subject',
                'title' => 'طلب اسم مرسل جديد',
                'details_heading' => 'تفاصيل طلب المرسل',
                'organization' => 'المؤسسة',
                'sender_name' => 'اسم المرسل المطلوب',
                'date' => 'التاريخ',
            ],
        ],
        'ticket' => [
            'subject-email' => "تم إنشاء التذكرة: :subject [التذكرة: :ticket_number]",
            'subject-email-replay' => "رد جديد: :subject [التذكرة: :ticket_number]",
            'number' => 'رقم التذكرة',
            'subject' => 'موضوع التذكرة',
            'status' => 'حالة التذكرة',
            'priority' => 'أولوية التذكرة',
            'created' => 'تاريخ الإنشاء',
            'support_team' => 'فريق الدعم',
            'no-content' => 'لا توجد محتويات',
            'detasils' => 'تم إنشاء تذكرة دعم جديدة بناءً على طلبك. سيقوم فريق الدعم بمراجعة تذكرتك والرد في أقرب وقت ممكن.',
            'attachments' => 'المرفقات',
            'has_replied' => 'قام فريق الدعم بالرد على تذكرتك',
            'new_ticket_created' => 'تم إنشاء تذكرة دعم جديدة',
            'new_reply' => 'رد جديد على تذكرة الدعم الخاصة بك',
            'view' => 'عرض التذكرة',
        ],
    ],

];