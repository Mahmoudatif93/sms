<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MessageDetails;
use App\Services\Sms;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrepareMessageDetails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $outbox;
    protected $sms;
    public $timeout = 3600;
    public $tries = 2;

    public function __construct($outbox, Sms $sms)
    {
        $this->outbox = $outbox;
        $this->sms = $sms;
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            \Log::info("Starting ULTRA FAST message preparation for ID: {$this->outbox->message_id}");

            // 1. فك تشفير JSON مرة واحدة فقط
            $numbers = json_decode($this->outbox->all_numbers, true); // استخدام array بدلاً من object
            $totalNumbers = count($numbers);
            
            // 2. تحسين قاعدة البيانات قبل الإدراج
            // $this->optimizeDatabaseForBulkInsert();

            // 3. استخدام أكبر batch size ممكن
            $batchSize = 5000; // زيادة كبيرة
            $currentTime = Carbon::now()->toDateTimeString(); // تحويل مرة واحدة
            
            // 4. معالجة فائقة السرعة
            $this->processBatchesUltraFast($numbers, $batchSize, $currentTime, $totalNumbers);

            // 5. استعادة إعدادات قاعدة البيانات
            // $this->restoreDatabaseSettings();

            // 6. حذف outbox
            $this->outbox->delete();

            $executionTime = round(microtime(true) - $startTime, 2);
            
            \Log::info("ULTRA FAST preparation completed", [
                'message_id' => $this->outbox->message_id,
                'total_numbers' => $totalNumbers,
                'execution_time' => $executionTime . ' seconds',
                'records_per_second' => round($totalNumbers / $executionTime, 2)
            ]);

        } catch (\Exception $e) {
            // استعادة الإعدادات حتى لو فشلت العملية
            $this->restoreDatabaseSettings();
            
            \Log::error("ULTRA FAST preparation failed", [
                'message_id' => $this->outbox->message_id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function optimizeDatabaseForBulkInsert(): void
    {
        DB::statement('SET SESSION autocommit = 0');
        DB::statement('SET SESSION unique_checks = 0');
        DB::statement('SET SESSION foreign_key_checks = 0');
        DB::statement('SET SESSION sql_log_bin = 0');
        
        // تعطيل المؤشرات مؤقتاً (اختياري - احذر!)
        // DB::statement('ALTER TABLE message_details DISABLE KEYS');
    }

    private function restoreDatabaseSettings(): void
    {
        // DB::statement('ALTER TABLE message_details ENABLE KEYS');
        DB::statement('SET SESSION autocommit = 1');
        DB::statement('SET SESSION unique_checks = 1');
        DB::statement('SET SESSION foreign_key_checks = 1');
        DB::statement('SET SESSION sql_log_bin = 1');
    }

    private function processBatchesUltraFast($numbers, $batchSize, $currentTime, $totalNumbers): void
    {
        $processedNumbers = 0;
        $details_param = [];

        // استخدام array keys للسرعة
        $numberKeys = array_keys($numbers);
        $lastKey = end($numberKeys);

        foreach ($numbers as $index => $number) {
            // بناء السجل مباشرة كـ array
            $details_param[] = [
                'message_id' => $this->outbox->message_id,
                'text' => $this->outbox->variables_message == 1 ? $number['text'] : $this->outbox->text,
                'length' => $this->outbox->length,
                'number' => $number['number'],
                'country_id' => $number['country'],
                'operator_id' => 0,
                'cost' => $number['cost'],
                'status' => 0,
                'encrypted' => $this->outbox->encrypted,
                'key' => bin2hex(random_bytes(8)),
                'gateway_id' => 0,
                'created_at' => $currentTime
            ];

            // إدراج عند الوصول للحد الأقصى أو النهاية
            if (count($details_param) >= $batchSize || $index === $lastKey) {
                // استخدام RAW SQL للسرعة القصوى
                $this->bulkInsertRaw($details_param);
                
                $processedNumbers += count($details_param);
                $details_param = [];

                // تسجيل مبسط كل 25,000 سجل فقط
                if ($processedNumbers % 25000 === 0) {
                    \Log::info("Processing: {$processedNumbers}/{$totalNumbers}");
                }

                // تحرير الذاكرة كل 100,000 سجل
                if ($processedNumbers % 100000 === 0) {
                    gc_collect_cycles();
                }
            }
        }
    }

    private function bulkInsertRaw($data): void
    {
        if (empty($data)) return;

        // بناء SQL يدوياً للسرعة القصوى
        $table = (new MessageDetails())->getTable();
        $columns = array_keys($data[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';
        
        $values = [];
        foreach ($data as $row) {
            $escapedRow = array_map(function($value) {
                return is_null($value) ? 'NULL' : "'" . addslashes($value) . "'";
            }, $row);
            $values[] = '(' . implode(', ', $escapedRow) . ')';
        }
        
        $sql = "INSERT INTO `{$table}` ({$columnList}) VALUES " . implode(', ', $values);
        
        DB::unprepared($sql);
    }

    public function failed(\Throwable $exception): void
    {
        // $this->restoreDatabaseSettings();
        
        \Log::error("ULTRA FAST job failed permanently", [
            'message_id' => $this->outbox->message_id,
            'error' => $exception->getMessage()
        ]);
    }
}