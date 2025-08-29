<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('update-shipments');
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string',
            'priority' => 'nullable|in:regular,urgent',
            'deadline' => 'nullable|date|after:today',
            'status' => 'nullable|in:pending,approved,assigned,in_progress,completed,cancelled',
        ];
    }
}
