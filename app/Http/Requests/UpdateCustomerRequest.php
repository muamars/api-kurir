<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('customers', 'company_name')->ignore($this->route('customer')),
            ],
            'customer_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:700',
            'address' => 'sometimes|string',
            'is_active' => 'boolean',
            'category' => 'sometimes|in:customer,supplier',
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.unique' => 'Nama perusahaan sudah terdaftar.',
        ];
    }
}
