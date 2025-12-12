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
            'category_id' => [
                'nullable',
                'exists:shipment_categories,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $category = \App\Models\ShipmentCategory::find($value);
                        if ($category && ! $category->is_active) {
                            $fail('The selected category is not active.');
                        }
                    }
                },
            ],
            'vehicle_type_id' => [
                'nullable',
                'exists:vehicle_types,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $vehicleType = \App\Models\VehicleType::find($value);
                        if ($vehicleType && ! $vehicleType->is_active) {
                            $fail('The selected vehicle type is not active.');
                        }
                    }
                },
            ],
            'notes' => 'nullable|string',
            'priority' => 'nullable|in:regular,urgent',
            'deadline' => 'nullable|date|after:today',
            'status' => 'nullable|in:pending,approved,assigned,in_progress,completed,cancelled',
        ];
    }
}
