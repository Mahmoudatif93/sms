<?php

namespace App\Services;

use App\Models\Message;
use App\Models\AdminMessage;
use App\Models\AdminMessageDetails;
use App\Models\MessageDetails;
use App\Models\Sender;
use App\Models\Gateway;
use App\Models\GatewaySender;
use App\Models\GatewayUser;
use App\Jobs\SendSms;
use App\Models\Country;
use Carbon\Carbon;
use App\Helpers\Sms\MessageHelper;
use App\Http\Interfaces\Sms\NumberProcessorInterface;
use App\Services\Sms\IndividualNumberProcessor;
use App\Services\Sms\GroupNumberProcessor;
use App\Services\Sms\AdsNumberProcessor;
use App\Services\Sms\ExcelNumberProcessor;
use App\Services\Sms\VariableExcelNumberProcessor;

class Sms
{
    protected $fileUploadService;
    protected $numberProcessors;
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->numberProcessors = [];
        $this->registerNumberProcessor('G', new GroupNumberProcessor());
        $this->registerNumberProcessor('A', new AdsNumberProcessor());
        $this->registerNumberProcessor('N', new IndividualNumberProcessor()); // Example for individual, adjust if needed
        $this->registerNumberProcessor('E', new ExcelNumberProcessor($fileUploadService));
        $this->registerNumberProcessor('V', new VariableExcelNumberProcessor($fileUploadService));
    }

    protected function registerNumberProcessor($prefix, NumberProcessorInterface $processor)
    {
        $this->numberProcessors[$prefix] = $processor;
    }

    /**
     * Get number processor by prefix
     */
    public function getNumberProcessor($prefix)
    {
        return $this->numberProcessors[$prefix] ?? null;
    }

    /**
     * Check if number processor exists for prefix
     */
    public function hasNumberProcessor($prefix)
    {
        return isset($this->numberProcessors[$prefix]);
    }


    public static function sendCampaign($message_id, $variables_message, $default_gateway_id, $model = "message")
    {
        $messageModel = $model == "admin_message" ? new AdminMessage() : new Message();

        $message = $messageModel->findOrFail($message_id);
        $message->proccess();
        $sender = Sender::where(['status' => 1, 'user_id' => $message->user_id, 'name' => $message->sender_name])->first();
        $sender_id = empty($sender) ? 0 : $sender->id; // test when sender empty
        $gateway = self::get_by_message_info($message->user_id, $sender_id);

        $is_use_default_gateway = false;
        if (empty($gateway)) {
            $default_gateway = Gateway::find($default_gateway_id);
            if ($default_gateway && $default_gateway->status == "1") {
                $is_use_default_gateway = true;
                $gateway = $default_gateway;
            }
        }
        if (empty($gateway)) {
            $gateway = self::get_first_one();
        }

        if (!empty($gateway)) {
            //TODO: handel With Varaibale
                $numbers = $message->getNumbers($gateway->max_number);
                while (!empty($numbers)) {
                    \Log::info('Gateway URL', ['en_url' => $gateway->en_url]);
                    dispatch(new SendSms($message->sender_name, $numbers, $message->text, $message_id, $sender->is_hlr ?? 0, $gateway->en_url, $gateway->id, $model))->onQueue('sms-high');
                    $numbers = $message->getNumbers($gateway->max_number);
                }
        }

        $message->unProccess();
    }


    private static function get_by_message_info($user_id = 0, $sender_id = 0)
    {
        $res = self::get_by_sender_id($sender_id);
        if (empty($res)) {
            $res = self::get_by_user_id($user_id);
        }
        if (empty($res)) {
            $res = self::get_by_operator_id(null);
        }
        if (empty($res)) {
            $res = self::get_by_country_id(null);
        }

        return $res;
    }

    //TODO: test when sender not use gateway
    private static function get_by_sender_id($sender_id)
    {
        if (!empty($sender_id)) {
            $gatway_sender = GatewaySender::where('sender_id', $sender_id)->first();
            if ($gatway_sender) {
                return Gateway::find($gatway_sender->gateway_id);
            }
        }
        return null;
    }
    //TODO: test when user  use gateway

    private static function get_by_user_id($user_id)
    {
        if (!empty($user_id)) {
            $gatway_user = GatewayUser::where('sender_id', $user_id)->first();
            if ($gatway_user) {
                return Gateway::find($gatway_user->gateway_id);
            }
        }
        return null;
    }

    private static function get_by_operator_id($operator_id)
    {
        return null;
    }

    private static function get_by_country_id($operator_id)
    {
        return null;
    }

    private static function get_first_one()
    {
        return Gateway::where('status', 1)->first();
    }

    public  function sendMessage($modelClass, $detailsModelClass, $senderName, $allNumbers, $message, $arrayParams = null, $smsType = 'NORMAL', $userId = 0)
    {
        $all_numbers = $this->processNumbers($allNumbers);
        if (!empty($arrayParams)) {
            $paramNames = array_keys($arrayParams);
            $paramValues = array_values($arrayParams);
            $message = str_replace($paramNames, $paramValues, $message);
        }

        $leng = calc_message_length($message, null);
        $entries = [];
        $numberArr = [];
        $this->processAllNumbers($all_numbers, null, $entries, $leng, $numberArr, $message, $smsType,null);
        $count = array_reduce($entries, fn($carry, $entry) => $carry + $entry['cnt'], 0);
        $cost = array_reduce($entries, fn($carry, $entry) => $carry + $entry['cost'], 0);

        $data = $this->createMessageData($senderName, $message, $count, $cost, $leng);
        $data['user_id'] = $userId;
        $messageModel = $modelClass::create($data);
        $details_param = $this->createMessageDetailsParams($messageModel->id, $numberArr, $message, $leng);

        if ($detailsModelClass::insert($details_param)) {
            SMS::sendCampaign($messageModel->id, 0, 0, $userId === 0 ? 'admin_message' : 'message');
        }
    }

    public function processNumbers(string $numbers): array
    {
        $all_numbers = str_replace(",", "\n", trim($numbers));
        return array_filter(explode("\n", $all_numbers), [$this, "checkIfEmpty"]);
    }

    public function processAllNumbers($all_numbers, $path, &$entries, $messageLong, &$numberArr, $message, $smsType,$user)
    {
        if($user){
            $countries = Country::get_active_by_user($user->id, $user->is_international??0);
        }else{
            //TODO: refactor
            $countries = Country::where('id',966)->get();
            // $countries = Country::all();

        }
        
        foreach ($all_numbers as $number) {
            $prefix = $this->getNumberPrefix($number, $smsType);
           
            if (isset($this->numberProcessors[$prefix])) {
                if ($prefix === "E" || $smsType === "VARIABLES") {
                    $number = $path;
                }
                $this->numberProcessors[$prefix]->process($number, $entries, $messageLong, $numberArr, $message, $countries);
            }
        }
    }

    public function getNumberPrefix($number, $smsType)
    {
        //TODO: switch 
        if (is_numeric(substr($number, 0, 1)) || substr($number, 0, 1)=="+") {
            return 'N';
        } elseif ($smsType === "VARIABLES") {
            return 'V';
        } elseif ($number === 'excel_file' && $smsType != "VARIABLES") {
            return 'E';
        } else {
            return \Str::upper($number[0]);
        }

    }

    protected function createMessageData($senderName, $message, $count, $cost, $leng)
    {
        return [
            'channel' => 'DIRECT',
            'user_id' => 0, // TODO: handle with supervisor id
            'text' => $message,
            'count' => $count,
            'cost' => $cost,
            'length' => $leng,
            'creation_datetime' => Carbon::now(),
            'sending_datetime' => null,
            'repeation_period' => 0,
            'repeation_times' => 0,
            'variables_message' => 0,
            'sender_name' => $senderName,
            'encrypted' => 0,
            'auth_code' => randomAuthCode(),
            'advertising' => 0,
            'sent_cnt' => 0,
            'lang' => MessageHelper::calcMessageLang($message),
        ];
    }

    protected function createMessageDetailsParams($messageId, $numberArr, $message, $leng)
    {
        return array_map(function ($number) use ($messageId, $message, $leng) {
            return [
                'message_id' => $messageId,
                'text' => $message,
                'length' => $leng,
                'number' => $number['number'],
                'country_id' => $number['country'],
                'operator_id' => 0,
                'cost' => $number['cost'],
                'status' => 0,
                'encrypted' => 0, 
                'gateway_id' => 0,
            ];
        }, $numberArr);
    }

    protected function checkIfEmpty($value)
    {
        return strlen(trim($value)) > 0;
    }
}

