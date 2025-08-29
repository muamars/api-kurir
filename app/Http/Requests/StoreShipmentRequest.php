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
        return [
            'notes' => 'nullable|string',
            'priority' => 'nullable|in:regular,urgent',
            'deadline' => 'nullable|date|after:today',

            'destinations' => 'required|array|min:1',
            'destinations.*.receiver_name' => 'required|string|max:255',
            'destinations.*.delivery_address' => 'required|string',
            'destinations.*.shipment_note' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'destinations.required' => 'At least one destination is required',
            'destinations.*.receiver_name.required' => 'Receiver name is required for each destination',
            'destinations.*.delivery_address.required' => 'Delivery address is required for each destination',
            'items.required' => 'At least one item is required',
            'items.*.item_name.required' => 'Item name is required',
            'items.*.quantity.required' => 'Item quantity is required',
            'items.*.quantity.min' => 'Item quantity must be at least 1',
        ];
    }
}
