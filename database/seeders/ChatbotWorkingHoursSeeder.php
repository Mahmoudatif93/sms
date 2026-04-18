<?php

namespace Database\Seeders;

use App\Models\ChatbotSettings;
use Illuminate\Database\Seeder;

class ChatbotWorkingHoursSeeder extends Seeder
{
    private const CHANNEL_ID = 'a060d2f8-af9a-443f-8448-b78a62b9b740';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = ChatbotSettings::where('channel_id', self::CHANNEL_ID)->first();

        if (!$settings) {
            $this->command->error('Chatbot settings not found for channel: ' . self::CHANNEL_ID);
            $this->command->info('Please run ChatbotLasuranSeeder first.');
            return;
        }

        $settings->update([
            // Working hours for each day
            'working_hours' => [
                'sunday' => ['start' => '10:00', 'end' => '22:00'],
                'monday' => ['start' => '10:00', 'end' => '22:00'],
                'tuesday' => ['start' => '10:00', 'end' => '22:00'],
                'wednesday' => ['start' => '10:00', 'end' => '22:00'],
                'thursday' => ['start' => '10:00', 'end' => '22:00'],
                'friday' => ['start' => '14:00', 'end' => '23:00'],  // Different hours for Friday
                'saturday' => ['start' => '10:00', 'end' => '22:00'],
            ],

            // Timezone
            'timezone' => 'Asia/Riyadh',

            // Outside hours message - Arabic
            'outside_hours_message_ar' => "نعتذر، نحن حالياً خارج ساعات العمل 🌙\n\n" .
                "🕙 ساعات العمل:\n" .
                "• يومياً: من 10:00 صباحاً حتى 10:00 مساءً\n" .
                "• الجمعة: من 2:00 ظهراً حتى 11:00 مساءً\n\n" .
                "📍 الموقع: Lasuran Beauty Spa – KAFD، الرياض\n\n" .
                "سيتم التواصل معكم من قبل فريقنا في أقرب وقت ممكن.\n" .
                "شكراً لتفهمكم! 🙏",

            // Outside hours message - English
            'outside_hours_message_en' => "We apologize, we are currently outside working hours 🌙\n\n" .
                "🕙 Working Hours:\n" .
                "• Daily: 10:00 AM to 10:00 PM\n" .
                "• Friday: 2:00 PM to 11:00 PM\n\n" .
                "📍 Location: Lasuran Beauty Spa – KAFD, Riyadh\n\n" .
                "Our team will contact you as soon as possible.\n" .
                "Thank you for your understanding! 🙏",
        ]);

        $this->command->info('✓ Working hours updated for Lasuran Beauty Spa channel');
        $this->command->info('  - Timezone: Asia/Riyadh');
        $this->command->info('  - Daily: 10:00 AM - 10:00 PM');
        $this->command->info('  - Friday: 2:00 PM - 11:00 PM');
    }
}
