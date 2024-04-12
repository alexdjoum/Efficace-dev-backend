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
            'has_garden' => 'required|boolean',
            'parkings' => 'required|integer|min:0',
            'has_pool' => 'required|boolean',
            'basement_area' => 'required|numeric|min:0',
            'ground_floor_area' => 'required|numeric|min:0',
            'type' => 'required|string',
            'description' => 'string',
            'location_id' => 'required|exists:locations,id',
            'country' => 'required|string',
            'city' => 'required|string',
            'street' => 'required|string',
            'coordinate_link' => 'required|string',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
        ];
    }
}