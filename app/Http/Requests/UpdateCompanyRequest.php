<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
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
            'company_id' => 'required',
            'company_name' => 'required|string',
            'owner_name' => 'required|string',
            'mobile' => 'required|digits_between:10,15',
            'aadhar_no' => 'nullable|string',
            'status' => 'required|string',
            'main_logo' => 'nullable|string',
            'sidebar_logo' => 'nullable|string',
            'favicon_icon' => 'nullable|string',
            'owner_image' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'details' => 'nullable|string|max:500',
        ];
    }
}
