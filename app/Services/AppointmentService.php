<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Land;
use App\Models\Property;
use App\Models\Product;

class AppointmentService
{
    public function create(array $data, $userId)
    {
        $product = Product::findOrFail($data['product_id']);

        $appointment = Appointment::create([
            'day' => $data['day'],
            'hour' => $data['hour'],
            'user_id' => $userId,
            'product_id' => $product->id,
            'status' => Appointment::STATUS_PENDING,
        ]);

        return $appointment->load(['product.productable', 'user']);
    }

    public function updateStatus($appointmentId, $status)
    {
        $appointment = Appointment::findOrFail($appointmentId);
        
        if (!in_array($status, [Appointment::STATUS_PENDING, Appointment::STATUS_DONE])) {
            throw new \Exception("Statut invalide. Utilisez 'In pending' ou 'Done'.");
        }

        $appointment->update(['status' => $status]);
        
        return $appointment->load(['product.productable', 'user']);
    }
}