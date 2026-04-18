<?php

namespace App\Services\Sms;
use App\Http\Interfaces\Sms\AbstractNumberProcessor;
use App\Services\FileUploadService;
use App\Services\SimpleXLSX;
use App\Helpers\Sms\NumberFormatter;

class ExcelNumberProcessor extends AbstractNumberProcessor
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function process($number, &$entries, $messageLong, &$numberArr, $message,$countries)
    {
        $path = $number;

        $fileExsit = $this->fileUploadService->getFileOss($path);
        if ($fileExsit) {
            $xlsx = new SimpleXLSX($path);
            $rows_cnt = 0;
            $rows = $xlsx->rowsFromTo(1, $rows_cnt, 50000 );
            while (!empty($rows)) {
                foreach ($rows as $value) {
                    $number = $value[0];
                    $number = NumberFormatter::formatNumber($number);
                    $country = $this->processCountry($number, $entries, $messageLong, $countries);
                    if (!$country) {
                        $this->addUndefinedCountry($entries);
                    } else {
                        $numberArr[] = [
                            'number' => $number,
                            'country' => $country['id'],
                            'cost' => $country['price'] * $messageLong
                        ];
                    }
                }
                $rows_cnt += 50000;
                $rows = $xlsx->rowsFromTo(1, $rows_cnt, 50000);
            }


            // $rows = SimpleExcelReader::create($path, 'xlsx')->noHeaderRow()->getRows();
            // $rows->each(function (array $rowProperties) use (&$entries, $messageLong, &$numberArr, $countries) {
            //     $country = $this->processCountry($rowProperties[0], $entries, $messageLong, $countries);
            //     if (!$country) {
            //         $this->addUndefinedCountry($entries);
            //     } else {
            //         $numberArr[] = [
            //             'number' => $rowProperties[0],
            //             'country' => $country['id'],
            //             'cost' => $country['price'] * $messageLong
            //         ];
            //     }


            // });

            // $this->fileUploadService->deleteFileOss($path);
        }


    }
}
