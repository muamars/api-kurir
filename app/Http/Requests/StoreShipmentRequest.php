<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create-shipments');
    }

    public function rules(): array
    {
        $rules = [
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
            'courier_notes' => 'nullable|string',
            'priority' => 'nullable|in:regular,urgent',
            'deadline' => 'nullable|date',
            'scheduled_delivery_datetime' => 'nullable|date',

            'destinations' => 'required|array|min:1',
            'destinations.*.receiver_company' => 'required|string|max:255',
            'destinations.*.receiver_name' => 'required|string|max:255',
            'destinations.*.receiver_contact' => 'required|string|max:20',
            'destinations.*.delivery_address' => 'required|string',
            'destinations.*.shipment_note' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.description' => 'nullable|string',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'destinations.required' => 'At least one destination is required',
            'destinations.*.receiver_company.required' => 'Receiver company is required for each destination',
            'destinations.*.receiver_name.required' => 'Receiver name is required for each destination',
            'destinations.*.receiver_contact.required' => 'Receiver contact is required for each destination',
            'destinations.*.delivery_address.required' => 'Delivery address is required for each destination',
            'items.required' => 'At least one item is required',
            'items.*.item_name.required' => 'Item name is required',
            'items.*.quantity.required' => 'Item quantity is required',
            'items.*.quantity.min' => 'Item quantity must be at least 1',
        ];
    }
}
