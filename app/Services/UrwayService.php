<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;

class UrwayService
{
    protected $method = 'POST';
    protected $endpoint = 'URWAYPGService/transaction/jsonProcess/JSONrequest';
    protected $live_url = 'https://payments.urway-tech.com/';
    protected $test_url = 'https://payments-dev.urway-tech.com/';
    protected $url;
    protected $terminal_id;
    protected $terminal_password;
    protected $secret_key;
    protected $attributes = [];
    protected $guzzleClient;
    protected $response;
    protected $mode;
    public function __construct()
    {
        $this->guzzleClient = new Client();
        $this->mode = Config::get('services.urway.mode');
        $this->terminal_id = Config::get('services.urway.terminal_id');
        $this->terminal_password = Config::get('services.urway.terminal_password');
        $this->secret_key = Config::get('services.urway.secret_key');
        $this->url = $this->mode === 'live' ? $this->live_url : $this->test_url;
    }

    public function getEndPointPath()
    {
        return $this->url . $this->endpoint;
    }

    public function setTrackId(string $trackId)
    {
        $this->attributes['trackid'] = $trackId;
        return $this;
    }

    public function setCustomerEmail(string $email)
    {
        $this->attributes['customerEmail'] = $email;
        return $this;
    }

    public function setCustomerIp($ip = '')
    {
        $this->attributes['merchantIp'] = request()->ip();
        return $this;
    }

    public function setCurrency(string $currency)
    {
        $this->attributes['currency'] = $currency;
        return $this;
    }

    public function setCountry(string $country)
    {
        $this->attributes['country'] = $country;
        return $this;
    }

    public function setAmount($amount)
    {
        $this->attributes['amount'] = $amount;
        return $this;
    }

    public function setRedirectUrl($url)
    {
        $this->attributes['udf2'] = $url;
        return $this;
    }

    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function mergeAttributes(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function hasAttribute($key)
    {
        return isset($this->attributes[$key]);
    }

    public function removeAttribute($key)
    {
        $this->attributes = array_filter($this->attributes, function ($name) use ($key) {
            return $name !== $key;
        }, ARRAY_FILTER_USE_KEY);

        return $this;
    }

    public function isSuccess($response)
    {
        return $response && $response['Result'] == 'Successful' && $response['ResponseCode'] == '000';
    }

    public function createPayment()
    {
        $this->setAuthAttributes();
        $this->generateRequestHash();

        $this->attributes['country'] = 'SA';
        try {
            $response = $this->guzzleClient->request(
                $this->method,
                $this->getEndPointPath(),
                [
                    'json' => $this->attributes,
                ]
            );

            $body = $response->getBody()->getContents();
            $data = json_decode($body);
            return $data;
        } catch (\Throwable $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            return $responseBodyAsString;
            throw new \Exception($e->getMessage());
        }
    }

    public function find(string $transaction_id,string $trackid)
    {
        $this->setAuthAttributes();
        $this->attributes['transid'] = $transaction_id;
        // $this->attributes['trackid'] = $trackid;
        // $this->generateFindRequestHash();


        try {
            $response = $this->guzzleClient->request(
                $this->method,
                $this->getEndPointPath(),
                [
                    'json' => $this->attributes,
                ]
            );

            return json_decode((string) $response->getBody());
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage());
        }
    }

    protected function generateRequestHash()
    {
        $requestHash = $this->attributes['trackid'] . '|' . $this->terminal_id . '|' . $this->terminal_password . '|' . $this->secret_key . '|' . $this->attributes['amount'] . '|' . $this->attributes['currency'];
        $this->attributes['requestHash'] = hash('sha256', $requestHash);
        $this->attributes['action'] = '1';
    }

    protected function generateFindRequestHash()
    {
        $requestHash = $this->attributes['trackid'] . '|' . $this->terminal_id . '|' . $this->terminal_password . '|' . $this->secret_key . '|' . $this->attributes['amount'] . '|' . $this->attributes['currency'];
        $this->attributes['requestHash'] = hash('sha256', $requestHash);
        $this->attributes['action'] = '10';
    }

    protected function setAuthAttributes()
    {
        $this->attributes['terminalId'] = $this->terminal_id;
        $this->attributes['password'] = $this->terminal_password;
        // $this->attributes['udf1'] = "{Dreams dsfsd sdfse, sefse sfse fsefs fsefsdx rdg.;re.}";
    }
}
