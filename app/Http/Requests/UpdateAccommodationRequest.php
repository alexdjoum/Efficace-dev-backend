<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccommodationRequest extends FormRequest
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
            'reference' => 'string|unique:accommodations',
            'dining_room' => 'sometimes|required|integer',
            'kitchen' => 'sometimes|required|integer',
            'bath_room' => 'sometimes|required|integer',
            'bedroom' => 'sometimes|required|integer',
            'living_room' => 'sometimes|required|integer',
            'description' => 'string',
            'type' => 'sometimes|required|string',
            'property_id' => 'sometimes|required|exists:properties,id',
            'images' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
        ];
    }
}
