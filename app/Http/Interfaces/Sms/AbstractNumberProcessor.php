<?php

namespace App\Http\Interfaces\Sms;

abstract class AbstractNumberProcessor implements NumberProcessorInterface
{
    protected function processCountry($number, &$entries, $messageLong, $countries)
    {
        $numberLength = strlen($number);
        foreach ($countries as $country) {
            if (preg_match("/^{$country['id']}/", substr($number, 0, 5))) {
                if ($numberLength >= $country['min_number_count'] && $numberLength <= $country['max_number_count']) {
                    if (!isset($entries[$country['id']])) {
                        $entries[$country['id']] = [
                            'id' => $country['id'],
                            'name_ar' => $country['name_ar'],
                            'name_en' => $country['name_en'],
                            'coverage_status' => $country['coverage_status'],
                            'cnt' => 0,
                            'cost' => 0
                        ];
                    }
                    $entries[$country['id']]['cnt']++;
                    $entries[$country['id']]['cost'] += $country['price'] * $messageLong;
                    return ['id' => $country['id'], 'price' => $country['price']];
                }
            }
        }
        return false;
    }
    protected function processCountry_($number, &$entries, $messageLong, $countries)
    {
        $numberLength = strlen($number);

        foreach ($countries as $country) {
            // Compile regex once
            $countryIdPattern = "/^{$country['id']}/";

            // Check if the number starts with the country ID and is within the allowed length range
            if (
                preg_match($countryIdPattern, substr($number, 0, 5)) &&
                $numberLength >= $country['min_number_count'] &&
                $numberLength <= $country['max_number_count']
            ) {

                // Initialize entry if not set
                // if (!isset($entries[$country['id']])) {
                //     $entries[$country['id']] = [
                //         'id' => $country['id'],
                //         'name_ar' => $country['name_ar'],
                //         'name_en' => $country['name_en'],
                //         'coverage_status' => $country['coverage_status'],
                //         'cnt' => 0,
                //         'cost' => 0
                //     ];
                // }

                // // Update count and cost
                // $entries[$country['id']]['cnt']++;
                // $entries[$country['id']]['cost'] += $country['price'] * $messageLong;

                return ['id' => $country['id'], 'price' => $country['price']];
            }
        }

        return false;
    }


    protected function addUndefinedCountry(&$entries)
    {
        if (!isset($entries[0])) {
            $entries[0] = [
                'id' => 0,
                'name_ar' => "غير محدد",
                'name_en' => "undefined",
                'coverage_status' => 0,
                'cnt' => 0,
                'cost' => 0
            ];
        }
        $entries[0]['cnt']++;
    }

    protected function processNumber($number){
            return str_replace('+', '', $number);
    }

    /**
     * Process multiple numbers in batch for better performance
     * This method can be overridden by child classes for optimized batch processing
     */
    public function processBatch(array $numbers, &$entries, $messageLong, &$numberArr, $message, $countries)
    {
        foreach ($numbers as $number) {
            $this->process($number, $entries, $messageLong, $numberArr, $message, $countries);
        }
    }

    /**
     * Optimized country processing for batch operations
     * Pre-compiles regex patterns for better performance
     */
    protected function processCountryBatch($number, &$entries, $messageLong, $countries, $precompiledPatterns = null)
    {
        $numberLength = strlen($number);

        // Use precompiled patterns if available for better performance
        if ($precompiledPatterns) {
            foreach ($countries as $index => $country) {
                $pattern = $precompiledPatterns[$index] ?? "/^{$country['id']}/";

                if (preg_match($pattern, substr($number, 0, 5))) {
                    if ($numberLength >= $country['min_number_count'] && $numberLength <= $country['max_number_count']) {
                        if (!isset($entries[$country['id']])) {
                            $entries[$country['id']] = [
                                'id' => $country['id'],
                                'name_ar' => $country['name_ar'],
                                'name_en' => $country['name_en'],
                                'coverage_status' => $country['coverage_status'],
                                'cnt' => 0,
                                'cost' => 0
                            ];
                        }
                        $entries[$country['id']]['cnt']++;
                        $entries[$country['id']]['cost'] += $country['price'] * $messageLong;
                        return ['id' => $country['id'], 'price' => $country['price']];
                    }
                }
            }
        } else {
            // Fallback to original method
            return $this->processCountry($number, $entries, $messageLong, $countries);
        }

        return false;
    }

    /**
     * Precompile regex patterns for countries to improve batch processing performance
     */
    protected function precompileCountryPatterns($countries)
    {
        $patterns = [];
        foreach ($countries as $index => $country) {
            $patterns[$index] = "/^{$country['id']}/";
        }
        return $patterns;
    }


}
