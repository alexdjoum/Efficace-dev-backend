<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreLandRequest extends FormRequest
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
            'area' => 'required|numeric',
            'is_fragmentable' => 'required|boolean',
            'relief' => 'required|string',
            'description' => 'string',
            'land_title' => 'file|mimes:png,jpg,pdf',
            'certificat_of_ownership' => 'file|mimes:png,jpg,pdf',
            'technical_doc' => 'file|mimes:png,jpg,pdf',
            'country' => 'required|string',
            'city' => 'required|string',
            'street' => 'required|string',
            'coordinate_link' => 'required|string',
            'images.*' => 'image|mimes:png,jpg,jpeg,svg|max:2048',
        ];
    }
}