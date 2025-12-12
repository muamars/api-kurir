<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Admin');
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission')->id;

        return [
            'name' => 'required|string|max:255|unique:permissions,name,'.$permissionId,
            'guard_name' => 'string|in:web,api',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Permission name is required',
            'name.unique' => 'Permission name already exists',
            'guard_name.in' => 'Guard name must be either web or api',
        ];
    }
}
