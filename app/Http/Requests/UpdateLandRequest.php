<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLandRequest extends FormRequest
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
            'area' => 'sometimes|required|numeric',
            'is_fragmentable' => 'sometimes|required|boolean',
            'relief' => 'sometimes|required|string',
            'description' => 'string',
            'land_title' => 'sometimes|required|string',
            'certificat_of_ownership' => 'sometimes|required|boolean',
            'technical_doc' => 'sometimes|required|boolean',
            'country' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'street' => 'sometimes|required|string',
            'coordinate_link' => 'sometimes|required|string',
            'images' => 'array',
            'images.*' => 'image|mimes:png,jpg,jpeg,svg|max:2048',
            'fragments' => 'array',
            'fragments.*' => 'required|numeric',
        ];
    }
}
