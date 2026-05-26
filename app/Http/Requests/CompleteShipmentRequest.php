<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteShipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('approve-shipments');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'required|exists:shipments,id',
            'shipping_cost' => 'required|integer|min:0',
            'vehicle_used' => 'required|string|max:255',
            'completion_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'online_tracking_url' => 'nullable|url|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'shipment_ids.required' => 'Pilih minimal satu shipment',
            'shipment_ids.array' => 'Format shipment IDs tidak valid',
            'shipment_ids.min' => 'Pilih minimal satu shipment',
            'shipment_ids.*.exists' => 'Shipment tidak ditemukan',
            'shipping_cost.required' => 'Biaya pengiriman wajib diisi',
            'shipping_cost.integer' => 'Biaya pengiriman harus berupa angka',
            'shipping_cost.min' => 'Biaya pengiriman tidak boleh negatif',
            'vehicle_used.required' => 'Jenis kendaraan wajib diisi',
            'vehicle_used.max' => 'Jenis kendaraan maksimal 255 karakter',
            'completion_photo.image' => 'File harus berupa gambar',
            'completion_photo.mimes' => 'Format gambar harus jpeg, png, atau jpg',
            'completion_photo.max' => 'Ukuran gambar maksimal 5MB',
        ];
    }
}
