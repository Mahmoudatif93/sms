<?php

namespace App\Jobs;

use App\Exports\WalletTransactionsExport;
use App\Mail\WalletExportMail;
use App\Models\Organization;
use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class ExportWalletTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $organizationId;
    protected $userEmail;
    protected $filters;
    protected $locale;

    public $timeout = 600;
    public $tries = 3;

    public function __construct(string $organizationId, string $userEmail, array $filters = [], string $locale = 'en')
    {
        $this->organizationId = $organizationId;
        $this->userEmail = $userEmail;
        $this->filters = $filters;
        $this->locale = $locale;
    }

    public function handle(FileUploadService $fileUploadService)
    {
        $organization = Organization::findOrFail($this->organizationId);

        $query = $organization->allTransactions()
            ->whereHas('wallet', function ($query) {
                $query->where('service_id', 2);
            });

        // Apply filters
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['category'])) {
            $query->where('category', $this->filters['category']);
        }

        if (!empty($this->filters['transaction_type'])) {
            $query->where('transaction_type', $this->filters['transaction_type']);
        }

        if (!empty($this->filters['from_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['from_date']);
        }

        if (!empty($this->filters['to_date'])) {
            $query->whereDate('created_at', '<=', $this->filters['to_date']);
        }

        $query->orderBy('created_at', 'desc');

        $fileName = 'wallet_transactions_' . $this->organizationId . '_' . time() . '.xlsx';
        $filePath = 'exports/' . $fileName;

        $excelContent = Excel::raw(new WalletTransactionsExport($query), \Maatwebsite\Excel\Excel::XLSX);

        $fileUploadService->uploadFromContent($excelContent, 'oss', $filePath);

        $fileUrl = $fileUploadService->getSignUrl($filePath, 3600);

        Mail::to($this->userEmail)->send(new WalletExportMail($fileUrl, $this->locale));
    }
}
