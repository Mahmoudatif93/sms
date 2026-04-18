<?php

namespace App\Http\Controllers\SmsUsers;

use App\Helpers\Sms\MessageHelper;
use App\Models\AdminMessage;
use App\Http\Controllers\BaseApiController;
use App\Http\Interfaces\Sms\NumberProcessorInterface;
use App\Services\FileUploadService;
use App\Models\AdminMessageDetails;
use App\Models\Message;
use App\Models\MessageDetails;
use App\Models\Country;
use App\Services\Sms;
use Carbon\Carbon;
abstract class AbstractSmsController extends BaseApiController
{
    protected $fileUploadService;
    protected $numberProcessors;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->numberProcessors = [];
    }

    

  

    

    

    

    

    

   
}
