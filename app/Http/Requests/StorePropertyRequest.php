<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
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
            'title' => 'required|string|min:3',
            'build_area' => 'required|numeric|min:0',
            'field_area' => 'required|numeric|min:0',
            'levels' => 'required|integer|min:0',
            'has_garden' => 'nullable|boolean',
            'parkings' => 'required|integer|min:0',
            'has_pool' => 'nullable|boolean',
            'basement_area' => 'sometimes|numeric|min:0',
            'ground_floor_area' => 'sometimes|numeric|min:0',
            'type' => 'required|string',
            'description' => 'required|string',
            'number_of_salons' => 'sometimes|integer|min:0',
            'images' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
        ];
    }
}