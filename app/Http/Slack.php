<?php

namespace App\Http;

use Illuminate\Support\Facades\Http;
use JetBrains\PhpStorm\NoReturn;

class Slack
{

    public static function Log($message, $file = __FILE__, $line = __LINE__, $platform = "whatsapp"): void
    {
        $environment = app()->environment();
        $file = self::formatDirectoryStructure($file);

        $image = match ($platform) {
            'messenger' => asset('images/messenger-red-icon.png'),
            default => asset('images/whatsapp-red-icon.png')
        };

        $payload = [
            "blocks" => [
                [
                    'type' => 'rich_text',
                    'elements' => [
                        [
                            'type' => 'rich_text_section',
                            'elements' => [
                                [
                                    'type' => 'text',
                                    'text' => '👀 Log: ',
                                    'style' => [
                                        'bold' => true
                                    ]
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'From: '
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $environment,
                                    'style' => [
                                        'bold' => true
                                    ]
                                ],
                                [
                                    'type' => 'text',
                                    'text' => ' Environment '
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "type" => "divider"
                ],
                [
                    "type" => "section",
                    "text" => [
                        "type" => "mrkdwn",
                        "text" => "From File: \n {$file} \n Line: {$line} \n {$message} "
                    ],
                    "accessory" => [
                        "type" => "image",
                        "image_url" => $image,
                        "alt_text" => "whatsapp thumbnail"
                    ]
                ]
            ]
        ];

        Http::post(config('services.slack.webhook_url'), $payload);

    }

    private static function formatDirectoryStructure($file): string
    {

        $segments = explode('/', $file);
        unset($segments[0]);


        $formattedPath = null;


        $currentIndent = 1; // Starting indentation level
        foreach ($segments as $key => $segment) {
            if ($key === count($segments)) {
                $formattedPath .= str_repeat("   ", $currentIndent - 1) . "└── $segment ✔️";
            } else {
                $formattedPath .= str_repeat("   ", $currentIndent - 1) . "├── $segment/";
            }
            $formattedPath .= "\n";
            $currentIndent++;
        }

        return $formattedPath;
    }


}
