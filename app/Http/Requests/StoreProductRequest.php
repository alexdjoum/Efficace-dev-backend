<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            "type" => "required|in:land,property,accommodation,virtual,retail_space",
            "for_rent" => "boolean|required_without:for_sale",
            "for_sale" => "boolean|required_without:for_rent",
            "productable_id" => ["required", "numeric", Rule::unique('products')
                ->where(function ($query) {
                    return $query
                        ->where('productable_type', "App\\Models\\" . str($this->input('type'))->title())
                        ->where('for_sale', $this->input('for_sale'))
                        ->where('productable_id', $this->input('productable_id'));
                })],
            "unit_price" => "required|numeric",
            "total_price" => "required|numeric",
            "description" => "string",
            "status" => "required|string",
        ];
    }
}
