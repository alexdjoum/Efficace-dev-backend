<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
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
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'build_area' => 'sometimes|numeric',
            'field_area' => 'sometimes|numeric',
            'levels' => 'sometimes|integer',
            'has_garden' => 'sometimes|boolean',
            'parkings' => 'sometimes|integer',
            'has_pool' => 'sometimes|boolean',
            'basement_area' => 'sometimes|numeric',
            'ground_floor_area' => 'sometimes|numeric',
            'type' => 'sometimes|string',
            'bedrooms' => 'sometimes|integer',
            'bathrooms' => 'sometimes|integer',
            'estimated_payment' => 'sometimes|numeric',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif',
        ];
    }
}
