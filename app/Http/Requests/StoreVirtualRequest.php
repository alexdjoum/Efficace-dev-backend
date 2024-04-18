<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreVirtualRequest extends FormRequest
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
            'property_id' => 'required|exists:properties,id',
            'archi_project_price' => 'required|numeric',
            'big_work_price' => 'required|numeric',
            'total_project_price' => 'required|numeric',
            'delivery_delay' => 'required|numeric',
            'description' => 'string',
            'building_permit_price' => 'required|numeric',
            'finishing_price' => 'required|numeric',
            'land_price' => 'required|numeric',
            'images' => 'array',
            'images.*' => 'image|mimes:png,jpg,svg,jpeg,gif'
        ];
    }
}
