<?php

namespace App\Services\Sms;
use App\Http\Interfaces\Sms\AbstractNumberProcessor;
use App\Services\FileUploadService;
use App\Services\SimpleXLSX;
use App\Helpers\Sms\NumberFormatter;
class VariableExcelNumberProcessor extends AbstractNumberProcessor
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
            $rows = $xlsx->rowsFromTo(1, $rows_cnt, 50000);
            while (!empty($rows)) {
                foreach ($rows as $value) {
                    $number = $value[0];
                    $number = NumberFormatter::formatNumber($number);
                    if (is_numeric($number)) {
                       
                        $replace_message = $message;
                        for ($col = 'A'; $col < 'Z'; $col++) {
                            if (isset($value[ord($col) - 65])) {
                                $replace_message = str_replace("{{$col}}", $value[ord($col) - 65], $message);
                            }
                        }
                    
                        $messageLong = calc_message_length($replace_message, 'VARIABLES');
                      
                        $country = $this->processCountry($number, $entries, $messageLong, $countries);
                        if (!$country) {
                            $this->addUndefinedCountry($entries);
                        } else {
                            $numberArr[] = [
                                'number' => $number,
                                'country' => $country['id'],
                                'cost' => $country['price'] * $messageLong,
                                'text' => $replace_message
                            ];
                        }
                    }

                }
                $rows_cnt += 50000;
                $rows = $xlsx->rowsFromTo(1, $rows_cnt, 50000);
            }
            // $rows = SimpleExcelReader::create($path, 'xlsx')->noHeaderRow()->getRows();
            // $rows->each(function (array $rowProperties) use (&$entries, $message, &$numberArr,$countries) {

            //     $number = $rowProperties[0];
            //     for ($col = 'A'; $col < 'Z'; $col++) {
            //         if (isset($rowProperties[ord($col) - 65])) {
            //             $message = str_replace("{{$col}}", $rowProperties[ord($col) - 65], $message);
            //         }
            //     }

            //     $messageLong = calc_message_length($message, 'VARIABLES');

            //     $country = $this->processCountry($number, $entries, $messageLong, $countries);
            //     if (!$country) {
            //         $this->addUndefinedCountry($entries);
            //     }

            //     $numberArr[] = [
            //         'number' => $number,
            //         'country' => $country['id'],
            //         'cost' => $country['price'] * $messageLong,
            //         'text' => $message
            //     ];

            // });
            // return true;
        }else{
           throw new \Exception('File not found');
        }
        $this->fileUploadService->deleteFileOss($path);
    }

    public function variableProcessExcel($path, &$entries, &$numberArr, $textMessage)
    {

    }
}
