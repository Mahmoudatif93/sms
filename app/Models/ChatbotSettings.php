<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotSettings extends Model
{
    use HasUuids;

    protected $table = 'chatbot_settings';

    protected $fillable = [
        'channel_id',
        'is_enabled',
        'use_knowledge_search',
        'whitelist_enabled',
        'whitelist_contacts',
        'welcome_message_ar',
        'welcome_message_en',
        'fallback_message_ar',
        'fallback_message_en',
        'system_prompt',
        'handoff_threshold',
        'handoff_keywords',
        'working_hours',
        'timezone',
        'outside_hours_message_ar',
        'outside_hours_message_en',
        'ai_model',
        'max_tokens',
        'temperature',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'use_knowledge_search' => 'boolean',
        'whitelist_enabled' => 'boolean',
        'handoff_keywords' => 'array',
        'working_hours' => 'array',
        'handoff_threshold' => 'integer',
        'max_tokens' => 'integer',
        'temperature' => 'float',
    ];

    protected $attributes = [
        'is_enabled' => false,
        'use_knowledge_search' => true,
        'whitelist_enabled' => false,
        'handoff_threshold' => 2,
        'timezone' => 'Asia/Riyadh',
        'ai_model' => 'gpt-4o-mini',
        'max_tokens' => 300,
        'temperature' => 0.3,
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function getWelcomeMessage(string $language = 'ar'): ?string
    {
        return $language === 'en' ? $this->welcome_message_en : $this->welcome_message_ar;
    }

    public function getFallbackMessage(string $language = 'ar'): ?string
    {
        return $language === 'en' ? $this->fallback_message_en : $this->fallback_message_ar;
    }

    public function getHandoffKeywords(): array
    {
        return $this->handoff_keywords ?? [
            'موظف',
            'بشري',
            'حقيقي',
            'أبي أكلم أحد',
            'حولني',
            'agent',
            'human',
            'representative',
            'talk to someone'
        ];
    }

    /**
     * Check if a contact is allowed to use the chatbot
     *
     * @param string|null $contactIdentifier Phone number or other identifier
     * @return bool
     */
    public function isContactAllowed(?string $contactIdentifier): bool
    {
        // If whitelist is not enabled, allow everyone
        if (!$this->whitelist_enabled) {
            return true;
        }

        // If whitelist is enabled but no contacts specified, block all
        if (empty($this->whitelist_contacts)) {
            return false;
        }

        // If no contact identifier provided, block
        if (empty($contactIdentifier)) {
            return false;
        }

        // Clean the contact identifier (remove spaces, +, etc.)
        $cleanedIdentifier = preg_replace('/[\s\+\-]/', '', $contactIdentifier);

        // Get whitelist as array and clean each entry
        $whitelist = array_map(function ($contact) {
            return preg_replace('/[\s\+\-]/', '', trim($contact));
        }, explode(',', $this->whitelist_contacts));

        // Check if contact is in whitelist
        return in_array($cleanedIdentifier, $whitelist);
    }

    public function getDefaultSystemPrompt(): string
    {
        return $this->system_prompt ?? <<<PROMPT
أنت مساعد خدمة عملاء ذكي.

القواعد:
1. أجب فقط من المعلومات المتوفرة في السياق
2. إذا لم تجد إجابة مناسبة، قل: "للأسف ما عندي معلومات كافية، تحب أحولك لموظف خدمة العملاء؟"
3. كن ودوداً ومختصراً
4. استخدم الإيموجي بشكل خفيف
5. إذا طلب العميل التحدث مع موظف، أخبره أنك ستحوله الآن
PROMPT;
    }

    /**
     * Check if current time is within working hours
     */
    public function isWithinWorkingHours(): bool
    {
        // If no working hours configured, assume always open
        if (empty($this->working_hours)) {
            return true;
        }

        $timezone = new \DateTimeZone($this->timezone ?? 'Asia/Riyadh');
        $now = new \DateTime('now', $timezone);

        // Get current day name (lowercase)
        $currentDay = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        // Check if today has working hours configured
        $todayHours = $this->working_hours[$currentDay] ?? null;

        // If no hours for today or explicitly closed
        if (!$todayHours || ($todayHours['closed'] ?? false)) {
            return false;
        }

        $start = $todayHours['start'] ?? null;
        $end = $todayHours['end'] ?? null;

        // If no start/end time, assume closed
        if (!$start || !$end) {
            return false;
        }

        // Check if current time is within range
        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * Get outside working hours message
     */
    public function getOutsideHoursMessage(string $language = 'ar'): string
    {
        $customMessage = $language === 'en'
            ? $this->outside_hours_message_en
            : $this->outside_hours_message_ar;

        if ($customMessage) {
            return $customMessage;
        }

        // Default message with working hours info
        return $this->getDefaultOutsideHoursMessage($language);
    }

    /**
     * Get default outside hours message with schedule info
     */
    private function getDefaultOutsideHoursMessage(string $language): string
    {
        $schedule = $this->getWorkingHoursDescription($language);

        if ($language === 'en') {
            return "Sorry, our customer service team is currently unavailable.\n\n" .
                "Working hours:\n{$schedule}\n\n" .
                "We'll get back to you as soon as possible. Thank you for your patience! 🙏";
        }

        return "نعتذر، فريق خدمة العملاء غير متاح حالياً.\n\n" .
            "ساعات العمل:\n{$schedule}\n\n" .
            "سنتواصل معك في أقرب وقت ممكن. شكراً لصبرك! 🙏";
    }

    /**
     * Get working hours description for display
     */
    public function getWorkingHoursDescription(string $language = 'ar'): string
    {
        if (empty($this->working_hours)) {
            return $language === 'ar' ? 'غير محدد' : 'Not specified';
        }

        $days = [
            'sunday' => ['ar' => 'الأحد', 'en' => 'Sunday'],
            'monday' => ['ar' => 'الإثنين', 'en' => 'Monday'],
            'tuesday' => ['ar' => 'الثلاثاء', 'en' => 'Tuesday'],
            'wednesday' => ['ar' => 'الأربعاء', 'en' => 'Wednesday'],
            'thursday' => ['ar' => 'الخميس', 'en' => 'Thursday'],
            'friday' => ['ar' => 'الجمعة', 'en' => 'Friday'],
            'saturday' => ['ar' => 'السبت', 'en' => 'Saturday'],
        ];

        $lines = [];
        foreach ($this->working_hours as $day => $hours) {
            $dayName = $days[$day][$language] ?? $day;

            if (!$hours || ($hours['closed'] ?? false)) {
                $lines[] = $language === 'ar'
                    ? "{$dayName}: مغلق"
                    : "{$dayName}: Closed";
            } else {
                $start = $hours['start'] ?? '??';
                $end = $hours['end'] ?? '??';
                $lines[] = $language === 'ar'
                    ? "{$dayName}: {$start} - {$end}"
                    : "{$dayName}: {$start} - {$end}";
            }
        }

        return implode("\n", $lines);
    }
}
