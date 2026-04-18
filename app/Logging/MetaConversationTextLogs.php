<?php

namespace App\Logging;

class MetaConversationTextLogs
{
    public const CSW_CLOSED_FAILED = <<<EOT
🚫 **Message Failed**
Meta returned error **131047 — Re-engagement message**.

---

💬 **What happened?**
You attempted to send a message to the customer, but more than **24 hours** have passed since their last reply.

---

⏳ **What is the Customer Service Window (CSW)?**
The CSW is a 24-hour period that starts when a customer messages your business.
During this time, you can freely send session messages (text, images, buttons, etc.).

---

❌ **Why was this message blocked?**
The CSW had already expired. WhatsApp blocks session messages outside this window for user protection.

---

✅ **What should you do?**
To re-engage the customer, you must send an **approved WhatsApp message template**.
This opens a new business-initiated conversation and complies with Meta’s messaging policy.

---

🔗 [Learn more](https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes/#131047)
EOT;

    public static function get(string $decision): ?string
    {
        return match ($decision) {
            'csw_closed_failed' => self::CSW_CLOSED_FAILED,
            'sent_successful' => '✅ **Message Sent**\nYour message was sent successfully and was billable under the **:category** category.',
            'delivered_successful' => '📬 **Message Delivered**\nThe message reached the recipient’s device.',
            'read_by_user' => '👁️ **Message Read**\nThe customer has read your message.',
            'delivery_failed_unknown' => '⚠️ **Unknown Failure**\nThe message could not be delivered. No specific error was provided by Meta.',
            default => null,
        };
    }
}
