<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Address;

class AddressSeeder extends Seeder
{
    public function run(): void
    {
        // Exemple pour un utilisateur
        Address::create([
            'street' => 'Avenue des Palmiers',
            'city' => 'Douala',
            'country' => 'Cameroun',
            'addressable_id' => 1,
            'addressable_type' => User::class,
        ]);

        // Exemple pour une entreprise
        Address::create([
            'street' => 'Boulevard de la Liberté',
            'city' => 'Bafoussam',
            'country' => 'Cameroun',
            'addressable_id' => 1,
            'addressable_type' => Company::class,
        ]);
    }
}
