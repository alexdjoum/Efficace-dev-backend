<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;
use App\Models\Address;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // Exemple : créer une location avec son adresse liée
        $location = Location::create([
            'coordinate_link' => '3.848, 11.502', // exemple de coordonnées
        ]);

        // Créer l'adresse associée via morphOne
        $location->address()->create([
            'street' => 'Avenue de la Liberté',
            'city' => 'Douala',
            'country' => 'Cameroun',
        ]);

        // Autre exemple
        $location2 = Location::create([
            'coordinate_link' => '4.051, 9.767',
        ]);

        $location2->address()->create([
            'street' => 'Boulevard du Marché',
            'city' => 'Yaoundé',
            'country' => 'Cameroun',
        ]);
    }
}
