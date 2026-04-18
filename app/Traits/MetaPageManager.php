<?php

namespace App\Traits;

use App\Models\MetaPage;
use App\Models\MetaPageAccessToken;
use Http;
use Log;

trait MetaPageManager
{
    private function getAllPages($businessManagerId, string $accessToken): array
    {
        $ownedPages = [];
        $baseEndpoint = "https://graph.facebook.com/v22.0/{$businessManagerId}/owned_pages";
        $params = [
            'fields' => 'access_token,id,name,about,bio,description,link,verification_status,website',
            'access_token' => $accessToken,
        ];

        do {
            $response = Http::get($baseEndpoint, $params);

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Failed to retrieve Business Pages.'];
            }

            $json = $response->json();
            $pages = $json['data'] ?? [];

            foreach ($pages as $page) {
                // Save to meta_pages table
                MetaPage::updateOrCreate(
                    ['id' => $page['id']],
                    [
                        'name' => $page['name'] ?? '',
                        'business_manager_account_id' => $businessManagerId,
                        'about' => $page['about'] ?? null,
                        'bio' => $page['bio'] ?? null,
                        'description' => $page['description'] ?? null,
                        'link' => $page['link'] ?? null,
                        'verification_status' => $page['verification_status'] ?? null,
                        'website' => $page['website'] ?? null,
                    ]
                );

                // Save access token
                MetaPageAccessToken::create([
                    'meta_page_id' => $page['id'],
                    'access_token' => $page['access_token'],
                ]);

                $ownedPages[] = [
                    'page_id' => $page['id'],
                    'name' => $page['name'],
                    'page_access_token' => $page['access_token'],
                ];
            }

            $afterCursor = data_get($json, 'paging.cursors.after');
            $hasNext = isset($json['paging']['next']);

            if ($hasNext && $afterCursor) {
                $params['after'] = $afterCursor;
            } else {
                break;
            }

        } while (true);

        return $ownedPages;
    }

    private function subscribePageToWebhook(string $pageId, string $pageAccessToken): array
    {
        $fields = implode(',', [
            'messages',
            'conversations',
            'messaging_postbacks',
            'message_deliveries',
            'message_reads',
            'messaging_optins',
            'messaging_referrals',
            'messaging_handovers',
            'standby',
            'messaging_account_linking',
            'feed',
            'mention',
        ]);

        $endpoint = "https://graph.facebook.com/v22.0/{$pageId}/subscribed_apps";

        $response = Http::asForm()->post($endpoint, [
            'subscribed_fields' => $fields,
            'access_token' => $pageAccessToken,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'message' => $response->json()['error']['message'] ?? 'Failed to subscribe to Page.',
        ];
    }


}
