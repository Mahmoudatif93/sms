<?php

namespace App\Helpers;

use InvalidArgumentException;

class TemplateValidationHelper
{
    public static function validateTextVariables(string $text): void
    {
        preg_match_all('/{{\d+}}/', $text, $matches);
        $placeholders = $matches[0];

        // If no placeholders are found, there's no need to validate further
        if (empty($placeholders)) {
            return; // No variables, so we skip further checks
        }

        $expectedNumber = 1;

//        // Validate that message does not start or end with a variable
//        $words = preg_split('/\s+/', $text); // Split the text into words
//        if (trim($words[0]) === $placeholders[0] || trim(end($words)) === $placeholders[count($placeholders) - 1]) {
//            throw new InvalidArgumentException("Message cannot start or end with a variable.");
//        }

        foreach ($placeholders as $placeholder) {
            $variableNumber = intval(trim($placeholder, '{}'));
            if ($variableNumber !== $expectedNumber) {
                throw new InvalidArgumentException("Invalid variable order in text: $text. Variables must start from {{1}} and increment sequentially.");
            }
            $expectedNumber++;
        }

        // Validate the variable to non-variable ratio
        $nonVariableWords = preg_replace('/{{\d+}}/', '', $text); // Remove variables
        $nonVariableWordCount = count(preg_split('/\s+/', trim($nonVariableWords))); // Count the remaining words
        $variableCount = count($placeholders);

        if ($variableCount > 0 && $nonVariableWordCount < (2 * $variableCount + 1)) {
            throw new InvalidArgumentException("Text does not meet the variable to non-variable word ratio. For every X variables, there must be 2X + 1 non-variable words.");
        }
    }

    public static function validateTextExample(string $text, array $example, string $componentType): void
    {

        $componentTextKey = strtolower($componentType) . '_text';


        // Extract the variables from the text
        preg_match_all('/{{\d+}}/', $text, $matches);
        $placeholders = $matches[0];

        // If no placeholders (variables) are found, no example validation is needed
        if (empty($placeholders)) {
            return; // No variables, so no need to validate the example
        }


        if (!isset($example[$componentTextKey]) || !is_array($example[$componentTextKey])) {
            throw new InvalidArgumentException("Invalid example format: $componentTextKey array is missing or not an array.");
        }

        $exampleText = $example[$componentTextKey];

        foreach ($placeholders as $placeholder) {
            $variableNumber = intval(trim($placeholder, '{}'));
            if (!isset($exampleText[0][$variableNumber - 1])) {
                throw new InvalidArgumentException("Missing example value for variable $placeholder in text: $text");
            }
        }
    }

    public static function validateTextLength(string $text, int $maxLength): void
    {
        if (mb_strlen($text) > $maxLength) {
            throw new InvalidArgumentException("Text exceeds maximum length of $maxLength characters.");
        }
    }
}
