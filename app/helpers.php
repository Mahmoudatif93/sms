<?php
use App\Models\Setting;
use App\Models\BadWordsLog;
use Illuminate\Support\Facades\Cache;

function decodeUnicodeEscape($str)
{
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($matches) {
        return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
    }, $str);
}

function check_message($message)
{
    $message_words = explode(" ", $message);
    $bad_words = explode(",", Setting::get_by_name('bad_words'));
    foreach ($message_words as $word) {
        if (in_array($word, $bad_words)) {

            BadWordsLog::insert(['user_id' => Auth::id(), 'badword' => $word]);
            return false;
        }
    }
    return true;
}

function calc_message_length_($message, $sms_type = null)
{
    $max_ar_char = 70;
    $max_en_char = 160;

    // Normalize line endings
    $message = str_replace("\r\n", "\n", $message);

    // Calculate the length of the message
    $typed_chars = mb_strlen($message, "utf-8");

    // Adjust for line breaks (each counted as 2 characters)
    $typed_chars += substr_count($message, "\n");

    // Additional characters for CALENDAR type
    if ($sms_type === 'CALENDAR') {
        $typed_chars += 36;
    }

    // Determine if the message is English
    $is_english = preg_match("/^[-a-zA-Z0-9_ \n\r\s,;:.!@£?#$&*+=\/<>'\"\^{})(%\-|]*$/", $message);
    //is_english($message);
    // preg_match("/^[-a-zA-Z0-9_ \n\r\s,;:.!@£?#$&*+=\/<>'\"\^{})(%\-|]*$/", $message);

    if ($is_english) {
        // English message
        if ($typed_chars > 160) {
            $max_en_char = 153;
        }
        $message_long = ceil($typed_chars / $max_en_char);
    } else {
        // Arabic or other message
        if ($typed_chars > 70) {
            $max_ar_char = 67;
        }
        $message_long = ceil($typed_chars / $max_ar_char);
    }

    return $message_long;
}

function is_english($message)
{
    $length = mb_strlen($message, 'utf-8');
    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($message, $i, 1, 'utf-8');
        // Check if character is within the allowed range
        if (
            !((ctype_alnum($char) || in_array($char, [' ', "\n", "\r", ',', ';', ':', '.', '!', '@', '£', '?', '#', '$', '&', '*', '+', '=', '/', '<', '>', '\'', '"', '^', '{', '}', ')', '(', '%', '-', '|'])))
        ) {
            return false; // Non-English character found
        }
    }
    return true; // Only English characters found
}
function calc_message_length($message, $sms_type = null)
{
    $max_ar_char = 70;
    $max_en_char = 160;
    $enter_length_value = 1;
    $typed_chars = 0;
    $message_long = 0;

    // Normalize line endings
    $message = str_replace("\r\n", "\n", $message);

    // Get the length of the message
    $typed_chars = mb_strlen($message, "utf-8");

    // Fetch enter_length setting
    $enter_length = Setting::get_by_name('enter_length');
    if ($enter_length) {
        $enter_length_value = $enter_length;
    }

    // Adjust for newline length if required
    //TODO: get from setting
    if ($enter_length_value == 2) {
        $typed_chars += substr_count($message, "\n");
    }

    // Additional characters for CALENDAR type
    if ($sms_type == 'CALENDAR') {
        $typed_chars += 36;
    }

    // Determine if message is in English
    $is_english = preg_match("/^[-a-zA-Z0-9_ \n\r\s,;:.!@£?#$&*+=\/<>'\"\^{})(%\-|]*$/", $message);

    if ($is_english) {
        // English message
        if ($typed_chars > 160) {
            $max_en_char = 153;
        }
        $message_long = ceil($typed_chars / $max_en_char);
    } else {
        // Arabic or other message
        if ($typed_chars > 70) {
            $max_ar_char = 67;
        }
        $message_long = ceil($typed_chars / $max_ar_char);
    }

    return $message_long;
}


function server_time()
{
    $offsettime = Setting::get_by_name('offset_time');
    return date("H:i:s", strtotime("now -$offsettime minutes"));
}

function randomAuthCode()
{
    return rand(1000000, 9999999);
    ;
}

function extractLocationTitle($url)
{
    // Parse the URL to get the path
    $parsedUrl = parse_url($url);
    if (!isset($parsedUrl['path'])) {
        return null;
    }

    // Use a regular expression to extract the title from the path
    $path = $parsedUrl['path'];
    $pattern = '/\/place\/([^\/]+)\//';
    preg_match($pattern, $path, $matches);

    // If a match is found, decode the URL-encoded title
    if (isset($matches[1])) {
        $locationTitle = urldecode($matches[1]);
        return $locationTitle;
    }

    return null;
}

function get_lat_long($google_url)
{
    $url_coordinates_position = strpos($google_url, '@') + 1;
    $coordinates = [
        "long" => null,
        "lat" => null
    ];

    if ($url_coordinates_position != false) {
        $coordinates_string = substr($google_url, $url_coordinates_position);
        $coordinates_array = explode(',', $coordinates_string);

        if (count($coordinates_array) >= 2) {
            $longitude = $coordinates_array[0];
            $latitude = $coordinates_array[1];

            $coordinates = [
                "long" => $longitude,
                "lat" => $latitude
            ];
        }

    }
    return $coordinates;
}

function getBoundary($contentType)
{
    if (preg_match('/boundary=(.*)$/', $contentType, $matches)) {
        return $matches[1];
    }
    return null;
}

function parseMultipartFormData($content, $boundary)
{
    $parts = [];
    $boundary = '--' . $boundary;
    $blocks = explode($boundary, $content);

    foreach ($blocks as $block) {
        if (empty(trim($block)) || $block == '--')
            continue;

        // Extract name and content
        if (preg_match('/name="([^"]*)"/', $block, $matches)) {
            $name = $matches[1];
            $pos = strpos($block, "\r\n\r\n");
            if ($pos !== false) {
                $value = substr($block, $pos + 4);
                // Remove trailing \r\n if present
                $value = preg_replace('/\r\n$/', '', $value);
                $parts[$name] = $value;
            }
        }
    }

    return $parts;
}


function return_front_url($path = '')
{
    $base_url = env('FRONT_APP_CLIENT_URL', 'https://portal.dreams.sa/ ');
    return rtrim($base_url, '/') . '/' . ltrim($path, '/');
}

/**
 * Get location data from an IP address.
 *
 * @param string $ipAddress The IP address to lookup
 * @return array|null Location data with city and country
 */
function getLocationFromIP(string $ipAddress): ?array
{
    // Skip for local/private IPs
    if (
        $ipAddress == '127.0.0.1' ||
        substr($ipAddress, 0, 3) == '10.' ||
        substr($ipAddress, 0, 7) == '192.168' ||
        substr($ipAddress, 0, 7) == '172.16.'
    ) {
        return null; // Fixed missing return statement
    }

    // Check cache first (store for 30 days)
    $cacheKey = 'ip_location_' . str_replace('.', '_', $ipAddress);

    return Cache::remember($cacheKey, 60 * 24 * 30, function () use ($ipAddress) {
        try {
            // Use ipapi.co for geolocation (free tier has rate limits)
            $response = Http::timeout(3)->get("https://ipapi.co/{$ipAddress}/json/");

            if ($response->successful()) {
                $data = $response->json();

                // Check for valid response
                if (isset($data['city']) || isset($data['country_name'])) {
                    return [
                        'city' => $data['city'] ?? null,
                        'country' => $data['country_name'] ?? null,
                        'country_code' => $data['country_code'] ?? null
                    ];
                }
            }

            // Fallback to ipinfo.io if first API fails
            $response = Http::timeout(3)->get("https://ipinfo.io/{$ipAddress}/json");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['city']) || isset($data['country'])) {
                    return [
                        'city' => $data['city'] ?? null,
                        'country' => $data['country'] ?? null,
                        'country_code' => $data['country'] ?? null
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log the error but don't stop execution
            \Log::error("IP Geolocation error: " . $e->getMessage());
        }

        return null;
    });
}