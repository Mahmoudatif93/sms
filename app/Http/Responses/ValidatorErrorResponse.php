<?php

namespace App\Http\Responses;

/**
 * @OA\Schema(
 *     schema="ValidatorErrorResponse",
 *     type="object",
 *     title="Validator Error Response",
 *     description="A response that contains validation error messages",
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="An object containing validation errors",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string", example="The field is required.")
 *         )
 *     )
 * )
 */

use App\Http\Interfaces\DataInterface;

class ValidatorErrorResponse extends DataInterface
{
    public array $errors;

    /**
     * @param array $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
    }
}
