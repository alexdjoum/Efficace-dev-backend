<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use FailValidation;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "type" => "sometimes|required|in:land,property,accommodation,virtual,retail_space",
            "productable_id" => "sometimes|required|numeric",
            "for_rent" => "sometimes|boolean|required_without:for_sale",
            "for_sale" => "sometimes|boolean|required_without:for_rent",
            "unit_price" => "sometimes|required|numeric",
            "total_price" => "sometimes|required|numeric",
            "description" => "string",
            "status" => "sometimes|required|string",
        ];
    }
}