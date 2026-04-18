<?php

namespace Database\Seeders;

use App\Models\ChatbotSettings;
use App\Models\ChatbotKnowledgeBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChatbotLasuranSeeder extends Seeder
{
    private const CHANNEL_ID = 'a060d2f8-af9a-443f-8448-b78a62b9b740';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createChatbotSettings();
        $this->createKnowledgeBase();

        $this->command->info('Chatbot settings and knowledge base created for Lasuran Beauty Spa!');
    }

    /**
     * Create chatbot settings for the channel
     */
    private function createChatbotSettings(): void
    {
        ChatbotSettings::updateOrCreate(
            ['channel_id' => self::CHANNEL_ID],
            [
                'id' => Str::uuid(),
                'is_enabled' => true,
                'welcome_message_ar' => "أهلًا وسهلًا بكم 🌿\nنرحّب بكم في Lasuran Beauty Spa – KAFD، وجهتكم الفاخرة للاسترخاء، للعناية بالجمال، وتجديد النشاط.\n\nكيف يمكننا مساعدتكم اليوم؟ ✨",
                'welcome_message_en' => "Welcome to Lasuran Beauty Spa – KAFD 🌿\nYour premium destination for relaxation, wellness, and beauty.\n\nHow may we assist you today? ✨",
                'fallback_message_ar' => "عذراً، لم أتمكن من فهم طلبك بشكل واضح 🙏\n\nهل تحب أن أحولك لأحد موظفينا للمساعدة؟",
                'fallback_message_en' => "I'm sorry, I couldn't fully understand your request 🙏\n\nWould you like me to connect you with one of our representatives?",
                'system_prompt' => $this->getSystemPrompt(),
                'handoff_threshold' => 2,
                'handoff_keywords' => [
                    'موظف', 'بشري', 'شخص', 'حولني', 'كلمني حد',
                    'agent', 'human', 'representative', 'talk to someone', 'speak to someone',
                    'شكوى', 'مشكلة', 'complaint', 'problem'
                ],
                'ai_model' => 'gpt-4o-mini',
                'max_tokens' => 400,
                'temperature' => 0.3,
            ]
        );

        $this->command->info('✓ Chatbot settings created');
    }

    /**
     * Get the system prompt for OpenAI
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
أنت مساعد ذكي لـ Lasuran Beauty Spa في مركز الملك عبدالله المالي (KAFD) بالرياض.

## معلومات أساسية:
- الاسم: Lasuran Beauty Spa
- الموقع: King Abdullah Financial District (KAFD), Riyadh
- ساعات العمل: يومياً من 10:00 صباحاً حتى 10:00 مساءً
- يوم الجمعة: من 2:00 ظهراً حتى 11:00 مساءً

## الخدمات المتوفرة:
1. صالون حلاقة رجالي
2. صالون نسائي متكامل
3. جلسات المساج والاسترخاء
4. العناية بالبشرة والوجه
5. حرملك وحمام (مغربي وتركي)
6. المانيكير والباديكير للرجال والسيدات

## تعليمات الرد:
1. رد باللغة التي يستخدمها العميل (عربي أو إنجليزي)
2. كن ودوداً ومهنياً ومختصراً
3. استخدم الإيموجي بشكل معتدل 🌿✨
4. إذا سأل عن الأسعار، اقترح تحميل التطبيق أو التحدث مع موظف
5. إذا كان الطلب خارج نطاق معرفتك، اقترح التحويل لموظف
6. لا تختلق معلومات غير موجودة

## أسلوب الرد:
- استخدم لغة راقية تناسب علامة تجارية فاخرة
- كن مساعداً وليس مبيعاً
- اختصر الردود قدر الإمكان
PROMPT;
    }

    /**
     * Create knowledge base entries
     */
    private function createKnowledgeBase(): void
    {
        $knowledge = $this->getKnowledgeData();

        foreach ($knowledge as $item) {
            ChatbotKnowledgeBase::updateOrCreate(
                [
                    'channel_id' => self::CHANNEL_ID,
                    'intent' => $item['intent'],
                ],
                array_merge($item, [
                    'id' => Str::uuid(),
                    'channel_id' => self::CHANNEL_ID,
                    'is_active' => true,
                    'keywords_text' => is_array($item['keywords']) ? implode(' ', $item['keywords']) : $item['keywords'],
                    'keywords' => is_array($item['keywords']) ? $item['keywords'] : json_decode($item['keywords'], true),
                ])
            );
        }

        $this->command->info('✓ Knowledge base created (' . count($knowledge) . ' items)');
    }

    /**
     * Get knowledge base data
     */
    private function getKnowledgeData(): array
    {
        return [
            [
                'intent' => 'greeting',
                'category' => 'عام',
                'keywords' => ['مرحبا', 'السلام', 'هلا', 'اهلا', 'hello', 'hi', 'hey', 'أهلا', 'السلام عليكم'],
                'question_ar' => 'السلام عليكم',
                'question_en' => 'Hello',
                'answer_ar' => "أهلًا وسهلًا بكم 🌿\nنرحّب بكم في Lasuran Beauty Spa – KAFD، وجهتكم الفاخرة للاسترخاء، للعناية بالجمال، وتجديد النشاط.\n\n🕙 ساعات العمل: يوميًا من 10:00 صباحًا حتى 10:00 مساءً\n🕌 يوم الجمعة: من 2:00 ظهرًا حتى 11:00 مساءً\n📍 الموقع: مركز الملك عبدالله المالي (KAFD) – الرياض\n\nيسعدنا خدمتكم، كيف يمكننا مساعدتكم اليوم؟ ✨",
                'answer_en' => "Welcome to Lasuran Beauty Spa – KAFD 🌿\nYour premium destination for relaxation, wellness, and beauty.\n\n🕙 Working Hours: Daily from 10:00 AM to 10:00 PM\n🕌 Friday: From 2:00 PM to 11:00 PM\n📍 Location: King Abdullah Financial District (KAFD), Riyadh\n\nHow may we assist you today? ✨",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 10,
            ],
            [
                'intent' => 'main_menu',
                'category' => 'عام',
                'keywords' => ['قائمة', 'خيارات', 'menu', 'options', 'اختيار', 'choose'],
                'question_ar' => 'ما هي الخيارات المتاحة؟',
                'question_en' => 'What options do you have?',
                'answer_ar' => "يرجى اختيار أحد الخيارات التالية:\n\n1️⃣ خدماتنا\n2️⃣ تحميل تطبيق Lasuran\n3️⃣ العروض الحالية\n4️⃣ أوقات العمل والموقع\n5️⃣ نقاط الولاء وخدمة التوصيل\n6️⃣ الشكاوى والمقترحات",
                'answer_en' => "Please choose from the options below:\n\n1️⃣ Our Services\n2️⃣ Download Lasuran App\n3️⃣ Current Offers\n4️⃣ Working Hours & Location\n5️⃣ Loyalty Points & Transportation\n6️⃣ Complaints & Suggestions",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 8,
            ],
            [
                'intent' => 'services',
                'category' => 'الخدمات',
                'keywords' => ['خدمات', 'خدمه', 'وش عندكم', 'ايش تقدمون', 'services', 'what do you offer', 'باقات', 'packages', 'صالون', 'salon', 'مساج', 'massage', 'حلاقة', 'barber'],
                'question_ar' => 'ما هي الخدمات المتوفرة؟',
                'question_en' => 'What services do you offer?',
                'answer_ar' => "نقدّم في Lasuran Beauty Spa مجموعة متكاملة من الخدمات الفاخرة:\n\n• صالون حلاقة رجالي\n• صالون نسائي متكامل\n• جلسات المساج والاسترخاء\n• العناية بالبشرة والوجه\n• حرملك وحمام\n• المانيكير والباديكير للرجال والسيدات\n\nهل تحب معرفة تفاصيل خدمة معيّنة أو نبدأ بالحجز مباشرة؟ 🌸",
                'answer_en' => "Discover our premium range of services and packages:\n\n• Men's Barbershop\n• Women's Beauty Salon\n• Massage & Relaxation Therapies\n• Facial & Skincare Treatments\n• Hammam and Harmalek\n• Manicure & Pedicure for men and women\n\nWould you like more details or shall we proceed with booking? 🌸",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 7,
            ],
            [
                'intent' => 'download_app',
                'category' => 'التطبيق',
                'keywords' => ['تطبيق', 'ابلكيشن', 'app', 'download', 'تحميل', 'نزل', 'جوال', 'mobile', 'google play', 'app store'],
                'question_ar' => 'كيف أحمل التطبيق؟',
                'question_en' => 'How can I download the app?',
                'answer_ar' => "لراحة أكبر وتجربة حجز سلسة، يمكنكم تحميل تطبيق Lasuran الآن:\n\n📲 Google Play\n📲 App Store\n\nمن خلال التطبيق يمكنكم:\n• حجز الخدمات بسهولة\n• الاطلاع على العروض الحصرية\n• جمع نقاط الولاء",
                'answer_en' => "Download the Lasuran App for an easy and seamless booking experience:\n\n📲 Google Play\n📲 App Store\n\nWith the app you can:\n• Book services easily\n• Access exclusive offers\n• Earn loyalty points",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'offers',
                'category' => 'العروض',
                'keywords' => ['عروض', 'خصم', 'تخفيض', 'offers', 'discount', 'promotion', 'نقاط', 'points', 'هدايا', 'gifts'],
                'question_ar' => 'ما هي العروض الحالية؟',
                'question_en' => 'What are your current offers?',
                'answer_ar' => "استمتعوا بعروض حصرية وتجربة متكاملة عبر تطبيق Lasuran:\n\n• الحصول على 100 نقطة ترحيبية عند التسجيل\n• خصومات مميزة على الخدمات\n• إمكانية إرسال الهدايا بكل سهولة\n\nهل تحب معرفة العروض المتاحة حاليًا أو الحجز مباشرة؟ ✨",
                'answer_en' => "Enjoy exclusive offers and rewards through the Lasuran App:\n\n• Get 100 welcome points upon registration\n• Special discounts on selected services\n• Easily send gifts to your loved ones\n\nWould you like to view current offers or proceed with booking? ✨",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'working_hours',
                'category' => 'معلومات عامة',
                'keywords' => ['وقت', 'ساعات', 'متى', 'مفتوح', 'دوام', 'hours', 'when', 'open', 'time', 'أوقات', 'working'],
                'question_ar' => 'ما هي ساعات العمل؟',
                'question_en' => 'What are your working hours?',
                'answer_ar' => "🕙 نعمل يوميًا من 10:00 صباحًا حتى 10:00 مساءً\n🕌 يوم الجمعة: من 2:00 ظهرًا حتى 11:00 مساءً\n\n📍 الموقع: Lasuran Beauty Spa – King Abdullah Financial District (KAFD), Riyadh\n\nيسعدنا استقبالكم في أي وقت 🌿",
                'answer_en' => "🕙 We're open daily from 10:00 AM to 10:00 PM\n🕌 Friday: From 2:00 PM to 11:00 PM\n\n📍 Location: Lasuran Beauty Spa – King Abdullah Financial District (KAFD), Riyadh\n\nWe look forward to welcoming you 🌿",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 6,
            ],
            [
                'intent' => 'location',
                'category' => 'معلومات عامة',
                'keywords' => ['وين', 'موقع', 'عنوان', 'فين', 'location', 'where', 'address', 'directions', 'كافد', 'kafd', 'الرياض', 'riyadh'],
                'question_ar' => 'ما هو العنوان؟',
                'question_en' => 'What is your address?',
                'answer_ar' => "📍 موقعنا:\nLasuran Beauty Spa – مركز الملك عبدالله المالي (KAFD)، الرياض\n\n🕙 ساعات العمل: يوميًا من 10:00 صباحًا حتى 10:00 مساءً\n🕌 يوم الجمعة: من 2:00 ظهرًا حتى 11:00 مساءً\n\nيسعدنا استقبالكم 🌿",
                'answer_en' => "📍 Our Location:\nLasuran Beauty Spa – King Abdullah Financial District (KAFD), Riyadh\n\n🕙 Working Hours: Daily from 10:00 AM to 10:00 PM\n🕌 Friday: From 2:00 PM to 11:00 PM\n\nWe look forward to welcoming you 🌿",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 6,
            ],
            [
                'intent' => 'loyalty_points',
                'category' => 'برنامج الولاء',
                'keywords' => ['نقاط', 'ولاء', 'points', 'loyalty', 'مكافآت', 'rewards', 'توصيل', 'transportation'],
                'question_ar' => 'ما هو برنامج نقاط الولاء؟',
                'question_en' => 'What is your loyalty points program?',
                'answer_ar' => "برنامج الولاء في Lasuran يمنحكم مزايا حصرية:\n\n• 100 نقطة ترحيبية عند التسجيل في التطبيق\n• اكسبوا نقاط مع كل زيارة\n• استبدلوا النقاط بخصومات وخدمات مجانية\n\n📲 حمّلوا التطبيق الآن للاستفادة من البرنامج!",
                'answer_en' => "The Lasuran Loyalty Program offers exclusive benefits:\n\n• 100 welcome points upon app registration\n• Earn points with every visit\n• Redeem points for discounts and free services\n\n📲 Download the app now to join the program!",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'booking',
                'category' => 'الحجز',
                'keywords' => ['حجز', 'احجز', 'موعد', 'book', 'appointment', 'reserve', 'schedule', 'أبي أحجز', 'ابغى احجز'],
                'question_ar' => 'كيف أحجز موعد؟',
                'question_en' => 'How can I book an appointment?',
                'answer_ar' => "يمكنكم الحجز بسهولة من خلال:\n\n📲 تطبيق Lasuran (الطريقة الأسرع)\n📞 الاتصال بنا مباشرة\n\nأو يمكنني تحويلك لأحد موظفينا للمساعدة في الحجز، هل تفضل ذلك؟ 🌸",
                'answer_en' => "You can easily book through:\n\n📲 Lasuran App (fastest way)\n📞 Call us directly\n\nOr I can connect you with a representative to help you book. Would you prefer that? 🌸",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 7,
            ],
            [
                'intent' => 'complaint',
                'category' => 'الشكاوى',
                'keywords' => ['شكوى', 'مشكلة', 'شكوه', 'complaint', 'problem', 'issue', 'unhappy', 'not satisfied', 'مقترح', 'suggestion', 'اقتراح'],
                'question_ar' => 'لدي شكوى أو مقترح',
                'question_en' => 'I have a complaint or suggestion',
                'answer_ar' => "شكرًا لتواصلكم معنا 🙏\n\nتم استلام رسالتكم بعناية، وسيقوم أحد مختصينا بالتواصل معكم في أقرب وقت ممكن.\n\nنقدّر وقتكم ونسعد دائمًا بخدمتكم في Lasuran Beauty Spa.",
                'answer_en' => "Thank you for reaching out to us 🙏\n\nYour message has been received with care. One of our specialists will contact you shortly.\n\nWe truly value your time and are always happy to assist you at Lasuran Beauty Spa.",
                'may_need_handoff' => false,
                'requires_handoff' => true,
                'priority' => 10,
            ],
            [
                'intent' => 'talk_to_agent',
                'category' => 'تحويل',
                'keywords' => ['موظف', 'بشري', 'شخص', 'agent', 'human', 'representative', 'حولني', 'كلمني', 'talk', 'speak', 'أبي أكلم', 'ابغى اكلم'],
                'question_ar' => 'أريد التحدث مع موظف',
                'question_en' => 'I want to talk to a representative',
                'answer_ar' => "بالتأكيد! 🌿\n\nجاري تحويلك لأحد موظفينا للمساعدة.\nيرجى الانتظار قليلاً وسيتم التواصل معكم في أقرب وقت.",
                'answer_en' => "Of course! 🌿\n\nConnecting you with one of our representatives.\nPlease wait a moment and someone will assist you shortly.",
                'may_need_handoff' => false,
                'requires_handoff' => true,
                'priority' => 10,
            ],
            [
                'intent' => 'thank_you',
                'category' => 'عام',
                'keywords' => ['شكرا', 'شكراً', 'مشكور', 'thank', 'thanks', 'appreciated', 'يعطيك العافية'],
                'question_ar' => 'شكراً',
                'question_en' => 'Thank you',
                'answer_ar' => "العفو! 🌿\n\nسعيدين بخدمتكم في Lasuran Beauty Spa.\nإذا احتجتم أي مساعدة أخرى، نحن هنا! ✨",
                'answer_en' => "You're welcome! 🌿\n\nHappy to help at Lasuran Beauty Spa.\nIf you need anything else, we're here! ✨",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 1,
            ],
            [
                'intent' => 'goodbye',
                'category' => 'عام',
                'keywords' => ['مع السلامة', 'باي', 'وداعا', 'goodbye', 'bye', 'see you', 'الى اللقاء'],
                'question_ar' => 'مع السلامة',
                'question_en' => 'Goodbye',
                'answer_ar' => "مع السلامة! 👋\n\nشكراً لتواصلكم مع Lasuran Beauty Spa.\nنتمنى لكم يوماً سعيداً ونتطلع لرؤيتكم قريباً! 🌿",
                'answer_en' => "Goodbye! 👋\n\nThank you for contacting Lasuran Beauty Spa.\nHave a great day and we look forward to seeing you soon! 🌿",
                'may_need_handoff' => false,
                'requires_handoff' => false,
                'priority' => 1,
            ],
            [
                'intent' => 'men_barbershop',
                'category' => 'الخدمات',
                'keywords' => ['حلاقة', 'رجال', 'barber', 'men', 'haircut', 'قص شعر', 'ذقن', 'beard'],
                'question_ar' => 'ما هي خدمات صالون الحلاقة الرجالي؟',
                'question_en' => 'What are men\'s barbershop services?',
                'answer_ar' => "صالون الحلاقة الرجالي في Lasuran يقدم:\n\n• قص الشعر الاحترافي\n• تهذيب وتصفيف اللحية\n• العناية بالبشرة للرجال\n• خدمات الحلاقة التقليدية\n\nهل تود الحجز الآن؟ 💈",
                'answer_en' => "Lasuran Men's Barbershop offers:\n\n• Professional haircuts\n• Beard trimming & styling\n• Men's skincare\n• Traditional grooming services\n\nWould you like to book now? 💈",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'women_salon',
                'category' => 'الخدمات',
                'keywords' => ['نسائي', 'سيدات', 'women', 'ladies', 'salon', 'شعر', 'hair', 'تسريحة', 'صبغة', 'color'],
                'question_ar' => 'ما هي خدمات الصالون النسائي؟',
                'question_en' => 'What are women\'s salon services?',
                'answer_ar' => "الصالون النسائي في Lasuran يقدم:\n\n• قص وتصفيف الشعر\n• صبغات وعلاجات الشعر\n• المكياج الاحترافي\n• العناية بالأظافر\n\nهل تودين الحجز الآن؟ 💅",
                'answer_en' => "Lasuran Women's Salon offers:\n\n• Hair cutting & styling\n• Hair coloring & treatments\n• Professional makeup\n• Nail care\n\nWould you like to book now? 💅",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'massage',
                'category' => 'الخدمات',
                'keywords' => ['مساج', 'massage', 'استرخاء', 'relaxation', 'سبا', 'spa', 'تدليك'],
                'question_ar' => 'ما هي خدمات المساج؟',
                'question_en' => 'What massage services do you offer?',
                'answer_ar' => "جلسات المساج والاسترخاء في Lasuran:\n\n• مساج سويدي\n• مساج الأحجار الساخنة\n• مساج الأنسجة العميقة\n• مساج الاسترخاء\n• العلاج بالروائح العطرية\n\nهل تود الحجز لجلسة استرخاء؟ 💆",
                'answer_en' => "Massage & Relaxation at Lasuran:\n\n• Swedish massage\n• Hot stone massage\n• Deep tissue massage\n• Relaxation massage\n• Aromatherapy\n\nWould you like to book a relaxation session? 💆",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'skincare',
                'category' => 'الخدمات',
                'keywords' => ['بشرة', 'وجه', 'skin', 'facial', 'skincare', 'تنظيف', 'عناية'],
                'question_ar' => 'ما هي خدمات العناية بالبشرة؟',
                'question_en' => 'What skincare services do you offer?',
                'answer_ar' => "خدمات العناية بالبشرة والوجه:\n\n• تنظيف البشرة العميق\n• علاجات الترطيب\n• مكافحة علامات التقدم بالعمر\n• علاجات حب الشباب\n• أقنعة الوجه المتخصصة\n\nهل تود الحجز لجلسة عناية؟ ✨",
                'answer_en' => "Facial & Skincare Treatments:\n\n• Deep cleansing facials\n• Hydration treatments\n• Anti-aging treatments\n• Acne treatments\n• Specialized face masks\n\nWould you like to book a skincare session? ✨",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'hammam',
                'category' => 'الخدمات',
                'keywords' => ['حمام', 'حرملك', 'hammam', 'turkish', 'مغربي', 'moroccan', 'بخار', 'steam'],
                'question_ar' => 'ما هي خدمات الحمام والحرملك؟',
                'question_en' => 'What are Hammam services?',
                'answer_ar' => "خدمات الحرملك والحمام في Lasuran:\n\n• الحمام المغربي التقليدي\n• الحمام التركي\n• جلسات البخار\n• التقشير بالصابون المغربي\n• العناية الشاملة بالجسم\n\nتجربة استرخاء لا تُنسى! 🛁",
                'answer_en' => "Hammam & Harmalek services at Lasuran:\n\n• Traditional Moroccan Hammam\n• Turkish Bath\n• Steam sessions\n• Moroccan soap exfoliation\n• Full body care\n\nAn unforgettable relaxation experience! 🛁",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 5,
            ],
            [
                'intent' => 'manicure_pedicure',
                'category' => 'الخدمات',
                'keywords' => ['مانيكير', 'باديكير', 'manicure', 'pedicure', 'أظافر', 'nails', 'يد', 'قدم'],
                'question_ar' => 'ما هي خدمات المانيكير والباديكير؟',
                'question_en' => 'What are manicure and pedicure services?',
                'answer_ar' => "خدمات المانيكير والباديكير للرجال والسيدات:\n\n• مانيكير كلاسيكي\n• باديكير سبا\n• العناية بالأظافر\n• تركيب الأظافر\n• علاجات اليدين والقدمين\n\nهل تود الحجز؟ 💅",
                'answer_en' => "Manicure & Pedicure for men and women:\n\n• Classic manicure\n• Spa pedicure\n• Nail care\n• Nail extensions\n• Hand & foot treatments\n\nWould you like to book? 💅",
                'may_need_handoff' => true,
                'requires_handoff' => false,
                'priority' => 5,
            ],
        ];
    }
}
