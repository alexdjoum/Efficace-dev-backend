<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccommodationRequest extends FormRequest
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
            'dining_room' => 'required|integer',
            'kitchen' => 'required|integer',
            'bath_room' => 'required|integer',
            'bedroom' => 'required|integer',
            'living_room' => 'required|integer',
            'description' => 'string',
            'type' => 'required|string',
            'property_id' => 'required|exists:properties,id',
            'images' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg',
        ];
    }
}
