<?php

namespace App\Http\Responses;

use App\Http\Whatsapp\WhatsappFlows\FlowValidationError;
use Http;


/**
 * @OA\Schema(
 *     schema="FlowDetails",
 *     type="object",
 *     @OA\Property(property="id", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(
 *         property="categories",
 *         type="array",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="validation_errors",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/FlowValidationError")
 *     ),
 *     @OA\Property(property="json_version", type="string"),
 *     @OA\Property(property="data_api_version", type="string"),
 *     @OA\Property(property="endpoint_uri", type="string", nullable=true),
 *     @OA\Property(
 *         property="preview",
 *         ref="#/components/schemas/Preview",
 *         nullable=true
 *     ),
 *     @OA\Property(property="whatsapp_business_account", type="object", nullable=true),
 *     @OA\Property(property="application", type="object", nullable=true),
 *     @OA\Property(
 *         property="health_status",
 *         ref="#/components/schemas/HealthStatus",
 *         nullable=true
 *     ),
 *          @OA\Property(
 *          property="json_content",
 *          type="array",
 *          @OA\Items(type="string")
 *      ),
 * )
 */
class FlowDetails
{
    public string $id;
    public string $name;
    public string $status;
    public array $categories;
    public array $validation_errors;
    public string $json_version;
    public ?string $data_api_version;
    public ?string $endpoint_uri;
    public ?Preview $preview;
    public ?array $whatsapp_business_account;
    public ?array $application;
    public ?HealthStatus $health_status;
    public ?array $assets;
    public $json_contents;

    public function __construct(array $flowDetails)
    {

        $downloadUrl = data_get($flowDetails, 'assets.data.0.download_url');
        $this->id = $flowDetails['id'];
        $this->name = $flowDetails['name'];
        $this->status = $flowDetails['status'];
        $this->categories = $flowDetails['categories'];
        $this->assets = $flowDetails['assets']['data'] ?? null;
        if ($downloadUrl) {
            $response = Http::get($downloadUrl);
            if ($response->successful()) {
                $this->json_contents =  json_decode($response->body());
            } else {
                $this->json_contents = null;
            }
        }
        $this->validation_errors = array_map(
            fn($error) => new FlowValidationError($error),
            $flowDetails['validation_errors']
        );
        $this->json_version = $flowDetails['json_version'];
        $this->data_api_version = $flowDetails['data_api_version'] ?? null;
        $this->endpoint_uri = $flowDetails['endpoint_uri'] ?? null;
        $this->preview = isset($flowDetails['preview']) ? new Preview($flowDetails['preview']) : null;
        // $this->whatsapp_business_account = $flowDetails['whatsapp_business_account'] ?? null;
        $this->application = $flowDetails['application'] ?? null;
        $this->health_status = isset($flowDetails['health_status']) ? new HealthStatus($flowDetails['health_status']) : null;
    }
}
