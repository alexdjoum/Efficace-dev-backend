<?php

namespace App\Http\Requests;

use App\Traits\FailValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePropositionRequest extends FormRequest
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
            'price' => 'sometimes|required|numeric',
            'status' => 'sometimes|required|string',
            'description' => 'string',
            'proposable_id' => 'sometimes|required|numeric',
            'type' => 'sometimes|required|string|in:land,property,accommodation,virtual,retail_space',
        ];
    }
}
