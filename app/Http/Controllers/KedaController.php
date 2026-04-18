<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class KedaController extends Controller
{
    public function index(Request $request)
    {
        // اختيار db 3
        Redis::select(3);

        $pattern = 'dreams_database_wa:company:*:buffer';
        $keys = [];
        $cursor = 0;

        // استخدم SCAN command مباشرة (يعمل مع Predis و phpredis)
        do {
            $result = Redis::command('scan', [$cursor, 'match', $pattern, 'count', 1000]);
            $cursor = (int) $result[0];

            if (!empty($result[1])) {
                $keys = array_merge($keys, $result[1]);
            }
        } while ($cursor !== 0);

        // إزالة التكرارات
        $keys = array_unique($keys);

        $total = 0;
        $summary = [];

        // استخدم pipeline للأداء الأفضل
        $chunks = array_chunk($keys, 500);

        foreach ($chunks as $chunk) {
            $results = Redis::pipeline(function ($pipe) use ($chunk) {
                // اختيار db 3 داخل الـ pipeline أيضاً
                $pipe->select(3);
                foreach ($chunk as $key) {
                    // إزالة الـ prefix لأن Laravel يضيفه تلقائياً
                    $cleanKey = str_replace('dreams_database_', '', $key);
                    $pipe->llen($cleanKey);
                }
            });

            // تخطي أول نتيجة لأنها نتيجة select
            array_shift($results);

            foreach ($chunk as $index => $key) {
                $count = $results[$index] ?? 0;
                $summary[$key] = $count;
                $total += $count;
            }
        }

        return response()->json([
            // 'companies' => $summary,
            'count' => $total,
            // 'keys_found' => count($keys)
        ]);
    }


    public function send_messanger_sms()
    {
     $x=   $this->sendMessengerMessage(
            '24747118314962221',        // PSID من الرسالة الواردة
            'مرحباً! كيف أقدر أساعدك؟',
            "EAALes6xZB1k8BQUAavCsTTuBvL18AFN0OnZC6yePeugnxylmTboBqLhQzbMNl9NECuuDTFN4F8K928P2iIz3lXpZBxBBx6tJBj35l3i8qoEPCFsYaGlZAj6FY33BDbhZCh51Ynvaodj1EvuL8rmm45eWfLo9ZAtMKbdDXOd0Kb9mR5gwXhmlcKR0ZBBTZBlMUkUazrze5ORa"
        );
        dd($x);
    }
    public function sendMessengerMessage(string $psid, string $message, string $pageAccessToken)
    {
        $response = \Http::post("https://graph.facebook.com/v22.0/me/messages", [
            'recipient' => [
                'id' => $psid
            ],
            'message' => [
                'text' => $message
            ],
            'access_token' => $pageAccessToken
        ]);

        return $response->json();
    }
}
