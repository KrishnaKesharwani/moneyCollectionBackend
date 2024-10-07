<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyPlanRequest extends FormRequest
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
            'plan' => 'required|string',
            'total_amount' => 'required|numeric',
            'advance_amount' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'detail' => 'required',
        ];
    }
}