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
            'title' => 'sometimes|required|string|min:3',
            'build_area' => 'sometimes|required|numeric|min:0',
            'field_area' => 'sometimes|required|numeric|min:0',
            'levels' => 'sometimes|required|integer|min:0',
            'has_garden' => 'sometimes|required|boolean',
            'parkings' => 'sometimes|required|integer|min:0',
            'has_pool' => 'sometimes|required|boolean',
            'basement_area' => 'sometimes|required|numeric|min:0',
            'ground_floor_area' => 'sometimes|required|numeric|min:0',
            'type' => 'sometimes|required|string',
            'description' => 'sometimes|required|string',
            'country' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'street' => 'sometimes|required|string',
            'file' => 'required|file|mimes:kml',
            'images' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
        ];
    }
}
