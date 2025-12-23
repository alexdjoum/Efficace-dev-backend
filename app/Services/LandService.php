<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Land;
use App\Models\Location;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;



class LandService
{
    public function create(array $data)
    {
        /** ==========================
         * 1️⃣ Location
         * ========================== */
        $location = Location::create([
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'street' => $data['street'] ?? null,
            'description' => $data['description'] ?? null,
            'coordinate_link' => null, 
        ]);

        if (isset($data['file'])) {
            $uploadedKml = $data['file'];
            
            if (strtolower($uploadedKml->getClientOriginalExtension()) !== 'kml') {
                throw new Exception("Le fichier doit être au format .kml");
            }

            $tempFilePath = $this->simplifyAndSaveKml(
                $uploadedKml->getPathname(),
                $uploadedKml->getClientOriginalName()
            );

            if (!file_exists($tempFilePath)) {
                throw new Exception("KML temporaire introuvable");
            }

            $media = $location
                ->addMedia($tempFilePath)
                ->usingFileName($uploadedKml->getClientOriginalName())
                ->toMediaCollection('kml');

            $location->update([
                'coordinate_link' => $media->getUrl(),
            ]);

            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }

        $address = Address::create([
            'country' => $data['country'],
            'city' => $data['city'],
            'street' => $data['street'],
        ]);

        $location->address()->save($address);

        $land = Land::create([
            'area' => $data['area'],
            'is_fragmentable' => $data['is_fragmentable'],
            'relief' => $data['relief'],
            'description' => $data['description'],
            'land_title' => $data['land_title'],
            'certificat_of_ownership' => $data['certificat_of_ownership'] ?? 0,
            'technical_doc' => $data['technical_doc'] ?? 0,
            'location_id' => $location->id,
        ]);

        if (isset($data['images'])) {
            collect($data['images'])->each(function ($image) use ($land) {
                $land->addMedia($image)->toMediaCollection('land');
            });
        }

        if (isset($data['fragments'])) {
            collect($data['fragments'])->each(function ($fragment) use ($land) {
                $land->fragments()->create(['area' => (float) $fragment]);
            });
        }

        if (isset($data['videoLink']) && trim($data['videoLink']) !== '') {
            $land->videoLands()->create([
                'videoLink' => trim($data['videoLink'])
            ]);
        }

        if (isset($data['videoLink']) && trim($data['videoLink']) !== '') {
            try {
                $land->videoLands()->create([
                    'videoLink' => trim($data['videoLink'])
                ]);
            } catch (\Exception $e) {
                \Log::error('Erreur création video_lands', [
                    'error' => $e->getMessage(),
                    'videoLink' => $data['videoLink']
                ]);
            }
        }

        if (isset($data['proposed_land_ids']) && is_array($data['proposed_land_ids'])) {
            collect($data['proposed_land_ids'])->each(function ($landId) use ($property) {
                $property->proposedSites()->create([
                    'proposable_id' => $landId,
                    'proposable_type' => Land::class,
                ]);
            });
        }

        $land->load([
            'fragments',
            'videoLands',
            'location',
            'location.address',
            'proposedSites.proposable',
        ]);

        // Forcer le chargement des médias
        $land->getMedia('land');
        $land->location?->getMedia('kml');

        $land->location->refresh();

        return $land;
    }

    private function simplifyAndSaveKml(string $originalPath, string $originalFileName): string
    {
        // 1. Charger le fichier
        $xml = @simplexml_load_file($originalPath); 

        if (!$xml) {
            throw new Exception("Impossible de charger le KML. Vérifiez le format XML.");
        }
        
        // 2. Créer un chemin de fichier temporaire
        $tempFilePath = tempnam(sys_get_temp_dir(), 'kml_min_'); 
        
        if ($tempFilePath === false) {
            throw new Exception("Impossible de créer un fichier temporaire pour le KML simplifié.");
        }

        // 3. Simplification : Supprimer les éléments inutiles
        
        // Supprimer <Schema> si présent au niveau Document
        if (isset($xml->Document->Schema)) {
            unset($xml->Document->Schema);
        }

        // Trouver tous les Placemark et supprimer ExtendedData
        $placemarks = $xml->xpath('//Placemark');
        foreach ($placemarks as $placemark) {
            if (isset($placemark->ExtendedData)) {
                unset($placemark->ExtendedData); 
            }
        }

        // 4. Sauvegarder la version simplifiée
        if (!$xml->asXML($tempFilePath)) {
            throw new Exception("Impossible de sauvegarder le KML simplifié.");
        }

        return $tempFilePath;
    }

    public function update(Land $land, array $data)
    {
        $land->location->update($data);
        $land->location->address->update($data);
        $land->update($data);


        if (isset($data['fragments'])) {
            $land->fragments()->delete();

            collect($data['fragments'])->each(function ($fragment) use ($land) {
                $land->fragments()->create(['area' => $fragment]);
            });
        }

        if (isset($data['images'])) {
            $land->clearMediaCollection('land');
            collect($data['images'])->each(function ($image) use ($land) {
                $land->addMedia($image)->toMediaCollection('land');
            });
        }

        return $land->fresh();
    }
}
