<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Land;
use App\Models\Address;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr; 
use App\Models\Location;

class LandSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer une location existante
        $location = Location::first();

        if ($location) {
            // Créer un Land lié à cette location
            Land::create([
                'area' => 1500,  // surface en m²
                'is_fragmentable' => true,
                'relief' => 'Plat',
                'description' => 'Terrain titré situé à Bonapriso, idéal pour projet immobilier.',
                'land_title' => 'TITRE-2025-001',
                'certificat_of_ownership' => 'Certificat No 5548/B',
                'technical_doc' => 'Plan topographique disponible',
                'location_id' => $location->id,
            ]);
        }

        // Autre exemple
        $location2 = Location::skip(1)->first();

        if ($location2) {
            Land::create([
                'area' => 2000,
                'is_fragmentable' => false,
                'relief' => 'Accidenté',
                'description' => 'Terrain en pente, idéal pour construction écologique.',
                'land_title' => 'TITRE-2025-002',
                'certificat_of_ownership' => 'Certificat No 5567/C',
                'technical_doc' => 'Plan topographique et étude géotechnique disponibles',
                'location_id' => $location2->id,
            ]);
        }
    }
}
