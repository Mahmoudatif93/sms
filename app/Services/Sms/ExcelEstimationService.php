<?php

namespace App\Services\Sms;

use App\Services\SimpleXLSX;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Log;

class ExcelEstimationService
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Estimate Excel file row count using multiple methods
     */
    public function estimateRowCount($filePath)
    {
        if (!$filePath) {
            return 50000; // Conservative fallback
        }
        // Try different estimation methods in order of accuracy
        $methods = [
            'getExactRowCount',
            'getQuickRowCount', 
            'estimateByFileSize',
            'getConservativeEstimate'
        ];

        foreach ($methods as $method) {
            try {
                $result = $this->$method($filePath);
                if ($result > 0) {
                    Log::info("Excel estimation successful", [
                        'file' => $filePath,
                        'method' => $method,
                        'estimated_rows' => $result
                    ]);
                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning("Excel estimation method failed", [
                    'file' => $filePath,
                    'method' => $method,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return 50000; // Final fallback
    }

    /**
     * Get exact row count by reading Excel metadata
     */
    private function getExactRowCount($filePath)
    {
        if (!$this->fileExists($filePath)) {
            return 0;
        }

        if (!class_exists(SimpleXLSX::class)) {
            return 0;
        }

        $xlsx = new SimpleXLSX($filePath);
        $dimension = $xlsx->dimension();
        
        if ($dimension && isset($dimension[1])) {
            // Subtract header row if exists
            return max(1, $dimension[1] - 1);
        }

        return 0;
    }

    /**
     * Get quick row count by sampling first few rows
     */
    private function getQuickRowCount($filePath)
    {
        if (!$this->fileExists($filePath)) {
            return 0;
        }

        if (!class_exists(SimpleXLSX::class)) {
            return 0;
        }

        try {
            $xlsx = new SimpleXLSX($filePath);
            
            // Sample first 100 rows to check data density
            $sampleRows = $xlsx->rowsFromTo(1, 1, 100);
            $nonEmptyRows = 0;
            
            foreach ($sampleRows as $row) {
                if (!empty($row[0]) && trim($row[0]) !== '') {
                    $nonEmptyRows++;
                }
            }
            
            if ($nonEmptyRows > 0) {
                // Estimate total based on sample
                $density = $nonEmptyRows / 100;
                $totalRows = $xlsx->dimension()[1] ?? 0;
                return intval($totalRows * $density);
            }
            
        } catch (\Exception $e) {
            Log::warning("Quick row count failed", [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }

        return 0;
    }

    /**
     * Estimate based on file size
     */
    private function estimateByFileSize($filePath)
    {
        if (!$this->fileExists($filePath)) {
            return 0;
        }

        $fileSize = $this->getFileSize($filePath);
        if ($fileSize <= 0) {
            return 0;
        }

        // Excel file size estimation factors
        $compressionRatio = 0.1; // Excel files are compressed
        $avgBytesPerRow = 50; // Conservative estimate for phone numbers
        
        $uncompressedSize = $fileSize / $compressionRatio;
        $estimatedRows = intval($uncompressedSize / $avgBytesPerRow);
        
        // Apply reasonable bounds
        return max(100, min(1000000, $estimatedRows));
    }

    /**
     * Conservative fallback estimate
     */
    private function getConservativeEstimate($filePath)
    {
        return 50000;
    }

    /**
     * Check if file exists (local or OSS)
     */
    private function fileExists($filePath)
    {
        // Check local file first
        if (file_exists($filePath)) {
            return true;
        }

        // Check OSS file
        try {
            return $this->fileUploadService->getFileOss($filePath);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file size (local or OSS)
     */
    private function getFileSize($filePath)
    {
        // Try local file first
        if (file_exists($filePath)) {
            return filesize($filePath);
        }

        // For OSS files, we might need to estimate differently
        // This could be enhanced to get actual OSS file size
        try {
            if ($this->fileUploadService->getFileOss($filePath)) {
                // Fallback estimation for OSS files
                return 1024 * 1024; // 1MB default
            }
        } catch (\Exception $e) {
            Log::warning("Could not get OSS file size", [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }

        return 0;
    }

    /**
     * Get detailed file analysis for debugging
     */
    public function analyzeFile($filePath)
    {
        $analysis = [
            'file_path' => $filePath,
            'exists' => $this->fileExists($filePath),
            'size' => $this->getFileSize($filePath),
            'methods' => []
        ];

        $methods = [
            'exact' => 'getExactRowCount',
            'quick' => 'getQuickRowCount',
            'size_based' => 'estimateByFileSize',
            'conservative' => 'getConservativeEstimate'
        ];

        foreach ($methods as $name => $method) {
            try {
                $result = $this->$method($filePath);
                $analysis['methods'][$name] = [
                    'success' => true,
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $analysis['methods'][$name] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $analysis;
    }
}
