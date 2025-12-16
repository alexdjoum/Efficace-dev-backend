<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Exemple de 5 customers
        for ($i = 1; $i <= 5; $i++) {

            // 1️⃣ Création du customer
            $customer = Customer::create([
                'first_name' => "Client{$i}",
                'last_name'  => "Test{$i}",
                'phone'      => "07000000{$i}",
                'type'       => "REGULAR",  // Ou PREMIUM, etc.
            ]);

        }
    }
}
