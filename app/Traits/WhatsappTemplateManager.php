<?php

namespace App\Traits;

use App\Http\Whatsapp\WhatsappTemplatesComponents\AuthenticationBodyComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\AuthenticationFooterComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\BodyComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\ButtonsComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\CopyCodeButton;
use App\Http\Whatsapp\WhatsappTemplatesComponents\FlowButton;
use App\Http\Whatsapp\WhatsappTemplatesComponents\FooterComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\HeaderLocationComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\HeaderMediaComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\HeaderTextComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\OtpButton;
use App\Http\Whatsapp\WhatsappTemplatesComponents\PhoneNumberButton;
use App\Http\Whatsapp\WhatsappTemplatesComponents\QuickReplyButton;
use App\Http\Whatsapp\WhatsappTemplatesComponents\UrlButton;
use App\Models\Channel;
use App\Models\TemplateBodyTextExample;
use App\Models\TemplateCopyCodeButton;
use App\Models\TemplateFlowButton;
use App\Models\TemplateHeaderMediaComponent;
use App\Models\TemplateHeaderTextComponent;
use App\Models\TemplateHeaderTextExample;
use App\Models\TemplatePhoneNumberButton;
use App\Models\TemplateQuickReplyButton;
use App\Models\TemplateUrlButton;
use App\Models\WhatsappAuthTemplateBodyComponent;
use App\Models\WhatsappAuthTemplateButtonComponent;
use App\Models\WhatsappAuthTemplateFooterComponent;
use App\Models\WhatsappAuthTemplateSupportedApp;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageTemplate;
use App\Models\WhatsappTemplateBodyComponent;
use App\Models\WhatsappTemplateButtonComponent;
use App\Models\WhatsappTemplateFooterComponent;
use App\Models\WhatsappTemplateHeaderComponent;
use App\Rules\WhatsappValidPhoneNumber;
use App\WhatsappMessages\Parameters\CurrencyParameter;
use App\WhatsappMessages\Parameters\DateTimeParameter;
use App\WhatsappMessages\Parameters\TextParameter;
use Exception;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Validator;

trait WhatsappTemplateManager
{
    use HeaderComponentBuilder,
        BodyComponentBuilder,
        ButtonComponentBuilder,
        FooterComponentBuilder;
    /**
     * Save the header component for a given template.
     *
     * @param int $templateId
     * @param HeaderTextComponent|HeaderLocationComponent|HeaderMediaComponent $headerComponent
     * @return void
     */
    public function saveHeaderComponent(int $templateId, HeaderTextComponent|HeaderLocationComponent|HeaderMediaComponent $headerComponent): void
    {
        // Clean up old headers if necessary
        WhatsAppTemplateHeaderComponent::where('template_id', $templateId)->delete();

        // Save the basic header component
        $headerComponentRecord = WhatsAppTemplateHeaderComponent::create([
            'template_id' => $templateId,
            'format' => $headerComponent->getFormat(),
        ]);

        // Handle saving the text header component
        if ($headerComponent instanceof HeaderTextComponent) {
            $this->saveTextHeaderComponent($headerComponentRecord->id, $headerComponent);
        }

        // Handle media header component logic (if needed)
        if ($headerComponent instanceof HeaderMediaComponent) {
            $this->saveMediaHeaderComponent($headerComponentRecord->id, $headerComponent);
        }

        // Handle location header component (if needed)
        if ($headerComponent instanceof HeaderLocationComponent) {
            $this->saveLocationHeaderComponent($headerComponentRecord->id, $headerComponent);
        }
    }

    /**
     * Save the text header component.
     *
     * @param int $headerComponentId
     * @param HeaderTextComponent $headerComponent
     * @return void
     */
    private function saveTextHeaderComponent(int $headerComponentId, HeaderTextComponent $headerComponent): void
    {
        // Update or create the text component
        $headerTextComponentRecord = TemplateHeaderTextComponent::updateOrCreate(
            ['header_component_id' => $headerComponentId],
            ['text' => $headerComponent->getText()]
        );

        // Save the example texts (if provided)
        $example = $headerComponent->getExample();
        if ($headerComponent->getFormat() === 'TEXT' && $example) {
            // Clear old examples
            TemplateHeaderTextExample::where('header_text_component_id', $headerTextComponentRecord->id)->delete();

            foreach ($example['header_text'] as $exampleText) {
                TemplateHeaderTextExample::create([
                    'header_text_component_id' => $headerTextComponentRecord->id,
                    'header_text' => $exampleText,
                ]);
            }
        }
    }


    /**
     * Save the media header component (if applicable).
     *
     * @param int $headerComponentId
     * @param HeaderMediaComponent $headerComponent
     * @return void
     */
    private function saveMediaHeaderComponent(int $headerComponentId, HeaderMediaComponent $headerComponent): void
    {
        $example = $headerComponent->getExample();

        if (!isset($example['header_handle'][0])) {
            throw new \InvalidArgumentException('Media header must include at least one header_handle.');
        }

        TemplateHeaderMediaComponent::updateOrCreate(
            ['header_component_id' => $headerComponentId],
            ['header_handle' => $example['header_handle'][0]]
        );
    }


    /**
     * Save the location header component (if applicable).
     *
     * @param int $headerComponentId
     * @param HeaderLocationComponent $headerComponent
     * @return void
     */
    private function saveLocationHeaderComponent(int $headerComponentId, HeaderLocationComponent $headerComponent): void
    {
        // No additional data is needed for LOCATION headers, but you can still add logic here if needed.
    }

    /**
     * Save the body component for a given template.
     *
     * @param int $templateId
     * @param BodyComponent $bodyComponent
     * @return void
     */
    public function saveBodyComponent(int $templateId, BodyComponent $bodyComponent): void
    {
        // Save the body component
        $bodyComponentRecord = WhatsappTemplateBodyComponent::create([
            'template_id' => $templateId,
            'text' => $bodyComponent->getText(),
        ]);

        // Save the text examples for the body (if available)
        $example = $bodyComponent->getExample()['body_text'][0] ?? null;
        if ($example) {
            foreach ($example as $exampleText) {
                TemplateBodyTextExample::create([
                    'body_text_component_id' => $bodyComponentRecord->id,
                    'body_text' => $exampleText,
                ]);
            }
        }
    }

    public function getTemplateBodyWithParameters(WhatsappMessage $whatsappMessage)
    {

        // Get the template text from the messageable relationship
        $templateMessage = $whatsappMessage->messageable;

        if (empty($templateMessage)) {
            return null;
        }

        $templateText = $templateMessage->whatsappTemplate->bodyComponent->text;

        // Check if there are any parameters in the template
        $hasVariables = str_contains($templateText, '{{');

        // If no variables exist, return the static template text as-is
        if (!$hasVariables) {
            return $templateText;
        }


        // Retrieve all parameters from the messageable body components
        $parameters = [];

        $bodyComponents = $templateMessage->bodyComponents;

        if ($bodyComponents) {
            // Collect body text parameters
            foreach ($bodyComponents->bodyTextParameters ?? [] as $textParam) {
                $parameters[] = $textParam->text ?? null;
            }

            // Collect currency parameters
            foreach ($bodyComponents->bodyCurrencyParameters ?? [] as $currencyParam) {
                $formattedCurrency = $currencyParam->code . ' ' . number_format($currencyParam->amount_1000 / 1000, 2);
                $parameters[] = $formattedCurrency;
            }

            // Collect date_time parameters
            foreach ($bodyComponents->bodyDateTimeParameters ?? [] as $dateTimeParam) {
                // Format the date_time as desired
                $formattedDateTime = "{$dateTimeParam->fallback_value}";
                $parameters[] = $formattedDateTime;
            }
        }

        // Replace placeholders {{1}}, {{2}}, {{3}}, etc. with actual parameters
        foreach ($parameters as $index => $param) {
            // Replace {{1}} with the first parameter, {{2}} with the second, etc.
            $templateText = str_replace('{{' . ($index + 1) . '}}', $param, $templateText);
        }

        // Output the final assembled text
        return $templateText;
    }

    public function getTemplateHeaderWithParameters(WhatsappMessage $whatsappMessage): ?array
    {
        $templateMessage = $whatsappMessage->messageable;
        if (empty($templateMessage)) {
            return null;
        }

        $headerComponent = $templateMessage->headerComponents;
        if (!$headerComponent) {
            return null;
        }

        // Image header
        if ($headerComponent->headerImageParameter) {
            return [
                'type' => 'image',
                'link' => $headerComponent->headerImageParameter?->media_link
            ];
        }

        // Text header
        if (method_exists($headerComponent, 'headerTextParameter') && $headerComponent->headerTextParameter) {
            return [
                'type' => 'text',
                'text' => $headerComponent->headerTextParameter?->text
            ];
        }

        // Location header
        if (method_exists($headerComponent, 'headerLocationParameter') && $headerComponent->headerLocationParameter) {
            return [
                'type' => 'location',
                'latitude'  => $headerComponent->headerLocationParameter?->latitude,
                'longitude' => $headerComponent->headerLocationParameter?->longitude,
                'name'      => $headerComponent->headerLocationParameter?->name,
                'address'   => $headerComponent->headerLocationParameter?->address,
            ];
        }

        return null;

    }

    public function getTemplateButtons(WhatsappMessage $whatsappMessage): ?array
    {
        $templateMessage = $whatsappMessage->messageable;
        if (empty($templateMessage)) {
            return null;
        }

        $template = $templateMessage->whatsappTemplate;
        if (empty($template)) {
            return null;
        }

        // Get button components from the template
        $buttonComponents = WhatsappTemplateButtonComponent::where('template_id', $template->id)->get();

        if ($buttonComponents->isEmpty()) {
            return null;
        }

        $buttons = [];

        foreach ($buttonComponents as $buttonComponent) {
            $buttonData = [
                'type' => $buttonComponent->type,
            ];

            switch (strtolower($buttonComponent->type)) {
                case 'url':
                    $urlButton = TemplateUrlButton::where('button_component_id', $buttonComponent->id)->first();
                    if ($urlButton) {
                        $buttonData['text'] = $urlButton->text;
                        $buttonData['url'] = $urlButton->url;
                        if ($urlButton->example) {
                            $buttonData['example'] = $urlButton->example;
                        }
                    }
                    break;

                case 'quick_reply':
                    $quickReplyButton = TemplateQuickReplyButton::where('button_component_id', $buttonComponent->id)->first();
                    if ($quickReplyButton) {
                        $buttonData['text'] = $quickReplyButton->text;
                    }
                    break;

                case 'phone_number':
                    $phoneButton = TemplatePhoneNumberButton::where('button_component_id', $buttonComponent->id)->first();
                    if ($phoneButton) {
                        $buttonData['text'] = $phoneButton->text;
                        $buttonData['phone_number'] = $phoneButton->phone_number;
                    }
                    break;

                case 'copy_code':
                    $copyCodeButton = TemplateCopyCodeButton::where('button_component_id', $buttonComponent->id)->first();
                    if ($copyCodeButton) {
                        $buttonData['example'] = $copyCodeButton->example;
                    }
                    break;

                case 'flow':
                    $flowButton = TemplateFlowButton::where('button_component_id', $buttonComponent->id)->first();
                    if ($flowButton) {
                        $buttonData['text'] = $flowButton->text;
                        $buttonData['flow_id'] = $flowButton->flow_id;
                        $buttonData['flow_action'] = $flowButton->flow_action;
                        if ($flowButton->navigate_screen) {
                            $buttonData['navigate_screen'] = $flowButton->navigate_screen;
                        }
                    }
                    break;
            }

            $buttons[] = $buttonData;
        }

        return empty($buttons) ? null : $buttons;
    }
    public function validateTemplateMessageRequest($request): array
    {
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()]
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()->toArray()];
        }

        return ['success' => true];
    }

    public function fetchTemplateFromAPI($templateId, $accessToken): array
    {

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "{$baseUrl}/{$version}/{$templateId}";
        $response = Http::withToken($accessToken)->get($url);
        if (!$response->successful()) {
            return ['success' => false, 'error' => json_decode($response->body())->error->message, 'status' => $response->status()];
        }

        return ['success' => true, 'template' => $response->json()];
    }

    public function validateAndBuildComponents(array $baseTemplate, array $requestTemplateComponents): array
    {
        $baseTemplateComponents = $baseTemplate['components'];
        $toSendComponents = [];


        foreach ($baseTemplateComponents as $index => $baseTemplateComponent) {
            $expectedType = strtolower($baseTemplateComponent['type'] ?? '');
            $requestTemplateComponent = $requestTemplateComponents[$index] ?? null;


            $result = match ($expectedType) {
                'header' => $this->buildHeaderComponent($baseTemplateComponent, $requestTemplateComponent),
                'body' => $this->buildBodyComponent($baseTemplateComponent, $requestTemplateComponent),
                'buttons' => $this->buildButtonsCollection($baseTemplateComponent, $requestTemplateComponent),
                'footer' => ['success' => true, 'component' => null],
                default => ['success' => false, 'error' => "Unsupported component type: {$expectedType}"],
            };

            if (!$result['success']) {
                return $result;
            }

            if (!empty($result['component'])) {
                $toSendComponents[] = $result['component'];
            }

            if (isset($result['components'])) {
                foreach ($result['components'] as $c) {
                    $toSendComponents[] = $c;
                }
                continue;
            }
        }

        return ['success' => true, 'components' => $toSendComponents];
    }

    public function buildParameter($parameter): DateTimeParameter|CurrencyParameter|TextParameter
    {
        switch ($parameter['type']) {
            case 'text':
                return new TextParameter($parameter['text'] ?? null);
            case 'currency':
                $currency = $parameter['currency'];
                return new CurrencyParameter($currency['fallback_value'] ?? null, $currency['code'] ?? null, $currency['amount_1000'] ?? null);
            case 'date_time':
                $date_time = $parameter['date_time'];
                return new DateTimeParameter(
                    $date_time['fallback_value'] ?? null,
                    $date_time['day_of_week'] ?? null,
                    $date_time['year'] ?? null,
                    $date_time['month'] ?? null,
                    $date_time['day_of_month'] ?? null,
                    $date_time['hour'] ?? null,
                    $date_time['minute'] ?? null
                );
            default:
                throw new InvalidArgumentException("Unsupported Parameter Type");
        }
    }

    public function templateHasVariables($template): bool
    {
        foreach ($template['components'] as $component) {
            if (isset($component['example'])) {
                return true;
            }

            if (
                isset($component['type'], $component['format']) &&
                $component['type'] === 'HEADER' &&
                $component['format'] === 'LOCATION'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save the body component for an authentication template.
     *
     * @param int $templateId
     * @param AuthenticationBodyComponent $bodyComponent
     * @return void
     */
    public function saveAuthenticationBodyComponent(int $templateId, AuthenticationBodyComponent $bodyComponent): void
    {

        WhatsappAuthTemplateBodyComponent::updateOrCreate([
            'template_id' => $templateId], [
            'add_security_recommendation' => $bodyComponent->getAddSecurityRecommendation()
        ]);

    }

    /**
     * Save the footer component for an authentication template.
     *
     * @param int $templateId
     * @param AuthenticationFooterComponent $footerComponent
     * @return void
     */
    public function saveAuthenticationFooterComponent(int $templateId, AuthenticationFooterComponent $footerComponent): void
    {
        // Save the footer component specific to authentication
        WhatsappAuthTemplateFooterComponent::updateOrCreate(
            [
                'template_id' => $templateId
            ],
            [
                'code_expiration_minutes' => $footerComponent->getCodeExpirationMinutes(), // Specific to authentication footer
            ]
        );
    }

    /**
     * Save the buttons component for an authentication template.
     *
     * @param int $templateId
     * @param OtpButton $buttonsComponent
     * @return void
     */
    public function saveAuthenticationButtonsComponent(int $templateId, OtpButton $buttonComponent): void
    {

        $buttonComponentRecord = WhatsappAuthTemplateButtonComponent::updateOrCreate([
            'template_id' => $templateId], [
            'otp_type' => $buttonComponent->getOtpType(), // e.g., copy_code, one_tap, zero_tap
            'text' => $buttonComponent->getText() ?? '', // Optional button text
            'autofill_text' => $buttonComponent->getAutofillText() ?? '', // Optional autofill text for one_tap or zero_tap
            'zero_tap_terms_accepted' => $buttonComponent->getZeroTapTermsAccepted() ?? false // Optional for zero_tap
        ]);

        $buttonComponentRecord->supportedApps()->delete(); // Deletes old ones

        // Save supported apps for zero_tap OTP buttons if they exist
        foreach ($buttonComponent->getSupportedApps() as $app) {
            WhatsappAuthTemplateSupportedApp::updateOrCreate([
                'button_component_id' => $buttonComponentRecord->id], [
                'package_name' => $app['package_name'],
                'signature_hash' => $app['signature_hash'],
            ]);
        }


    }


    protected function saveFooterComponent($templateId, FooterComponent $component): void
    {
        // Save the footer component to the database
        WhatsappTemplateFooterComponent::updateOrCreate([
            'template_id' => $templateId],[  // Link to the template
            'text' => $component->getText(),  // Save the footer text
        ]);
    }

    protected function saveButtonsComponent($templateId, ButtonsComponent $buttonsComponent): void
    {
        // Step 1: Delete existing button components and their child data
        $existingButtons = WhatsappTemplateButtonComponent::where('template_id', $templateId)->get();

        foreach ($existingButtons as $button) {

            TemplatePhoneNumberButton::whereButtonComponentId($button->id)->delete();
            TemplateUrlButton::where('button_component_id', $button->id)->delete();
            TemplateQuickReplyButton::where('button_component_id', $button->id)->delete();
            TemplateCopyCodeButton::where('button_component_id', $button->id)->delete();
            TemplateFlowButton::where('button_component_id', $button->id)->delete();
            $button->delete();
        }
        // Save each button in the buttons array
        foreach ($buttonsComponent->getButtons() as $button) {
            $buttonComponentRecord = WhatsappTemplateButtonComponent::create([
                'template_id' => $templateId,
                'type' => $button->getType(),
            ]);

            // Handle saving each button type
            if ($button instanceof PhoneNumberButton) {
                $this->savePhoneNumberButton($buttonComponentRecord->id, $button);
            }

            if ($button instanceof UrlButton) {
                $this->saveUrlButton($buttonComponentRecord->id, $button);
            }

            if ($button instanceof QuickReplyButton) {
                $this->saveQuickReplyButton($buttonComponentRecord->id, $button);
            }

            if ($button instanceof CopyCodeButton) {
                $this->saveCopyCodeButton($buttonComponentRecord->id, $button);
            }

            if ($button instanceof FlowButton) {
                $this->saveFlowButton($buttonComponentRecord->id, $button);
            }
        }
    }

    /**
     * Save the phone number button component.
     *
     * @param int $buttonComponentId
     * @param PhoneNumberButton $button
     * @return void
     */
    private function savePhoneNumberButton(int $buttonComponentId, PhoneNumberButton $button): void
    {
        TemplatePhoneNumberButton::create([
            'button_component_id' => $buttonComponentId,
            'text' => $button->getText(), // The label text for the button
            'phone_number' => $button->getPhoneNumber(), // The phone number to be called
        ]);
    }

    private function saveUrlButton(int $buttonComponentId, UrlButton $button): void
    {
        TemplateUrlButton::create([
            'button_component_id' => $buttonComponentId,
            'text' => $button->getText(),
            'url' => $button->getUrl(),
            'example' => $button->getExample()[0]?? null

        ]);
    }

    private function saveQuickReplyButton(int $buttonComponentId, QuickReplyButton $button): void
    {
        TemplateQuickReplyButton::create([
            'button_component_id' => $buttonComponentId,
            'text' => $button->getText(), // The label text for the button
        ]);
    }

    private function saveCopyCodeButton(int $buttonComponentId, CopyCodeButton $button): void
    {
        TemplateCopyCodeButton::create([
            'button_component_id' => $buttonComponentId,
            'example' => $button->getExample()[0]
        ]);
    }

    private function saveFlowButton(int $buttonComponentId, FlowButton $button): void
    {
        TemplateFlowButton::create([
            'button_component_id' => $buttonComponentId,
            'text' => $button->getText(),
            'flow_id' => $button->getFlowId(),
            'flow_json' => $button->getFlowJson(),
            'flow_action' => $button->getFlowAction(),
            'navigate_screen' => $button->getNavigateScreen(),
        ]);
    }



    public function getFooter(WhatsappMessage $message)
    {
        return $message->messageable?->whatsappTemplate?->footerComponent?->text ?? null;
    }


    public function getTemplateCategoryById(string $templateId, Channel $channel): ?string
    {

        $whatsappBusinessAccount = $channel->whatsappConfiguration->whatsappBusinessAccount;

        if (!$whatsappBusinessAccount) {
            return null; // Can't proceed without WABA
        }
        // Step 1: Try from DB first
        $templateFromDb = WhatsappMessageTemplate::where('id', $templateId)
            ->where('whatsapp_business_account_id', $whatsappBusinessAccount->id ?? null)
            ->first();

        if ($templateFromDb && $templateFromDb->category) {
            return strtolower($templateFromDb->category);
        }

        // Step 2: Fetch from API

        $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return null;
        }

        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $apiVersion = env('FACEBOOK_GRAPH_API_VERSION');
        $url = "{$baseUrl}/{$apiVersion}/{$templateId}";

        $response = Http::withToken($accessToken)->get($url);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if (empty($data) || !isset($data['category'])) {
            return null;
        }

        // Optional: Cache/update locally
        WhatsappMessageTemplate::updateOrCreate(
            [
                'id' => $data['id'],
                'whatsapp_business_account_id' => $whatsappBusinessAccount->id,
            ],
            [
                'name' => $data['name'] ?? null,
                'language' => $data['language'] ?? null,
                'status' => $data['status'] ?? null,
                'category' => $data['category'],
            ]
        );

        return strtolower($data['category']);
    }

    protected function deleteExistingComponents(int $templateId, string $category): void
    {
        if (strtolower($category) === 'authentication') {
            WhatsappAuthTemplateBodyComponent::where('template_id', $templateId)->delete();
            WhatsappAuthTemplateFooterComponent::where('template_id', $templateId)->delete();
            WhatsappAuthTemplateButtonComponent::where('template_id', $templateId)->each(function ($button) {
                WhatsappAuthTemplateSupportedApp::where('button_component_id', $button->id)->delete();
                $button->delete();
            });
        } else {
            WhatsappTemplateBodyComponent::where('template_id', $templateId)->delete();
            WhatsappTemplateFooterComponent::where('template_id', $templateId)->delete();
            WhatsappTemplateHeaderComponent::where('template_id', $templateId)->each(function ($header) {
                TemplateHeaderTextComponent::where('header_component_id', $header->id)->each(function ($textHeader) {
                    TemplateHeaderTextExample::where('header_text_component_id', $textHeader->id)->delete();
                    $textHeader->delete();
                });
                TemplateHeaderMediaComponent::where('header_component_id', $header->id)->delete();
                $header->delete();
            });
            WhatsappTemplateButtonComponent::where('template_id', $templateId)->each(function ($button) {
                TemplatePhoneNumberButton::where('button_component_id', $button->id)->delete();
                TemplateUrlButton::where('button_component_id', $button->id)->delete();
                TemplateQuickReplyButton::where('button_component_id', $button->id)->delete();
                TemplateCopyCodeButton::where('button_component_id', $button->id)->delete();
                TemplateFlowButton::where('button_component_id', $button->id)->delete();
                $button->delete();
            });
        }
    }

    protected function buildButtonsCollection(array $templateComponent, ?array $requestComponent): array
    {
        $components = [];

        foreach ($templateComponent['buttons'] as $btnIndex => $btnTemplate) {

            $result = $this->buildButtonComponent(
                $templateComponent,
                $requestComponent,
                $btnIndex
            );

            if (!$result['success']) {
                return $result;
            }

            if (!empty($result['component'])) {
                $components[] = $result['component'];
            }
        }

        return [
            'success'   => true,
            'components' => $components
        ];
    }


}
