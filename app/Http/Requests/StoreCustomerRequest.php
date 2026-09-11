<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'required|string|max:255|unique:customers,company_name',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:700',
            'address' => 'required|string',
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
