<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Land;
use App\Models\Location;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use App\Models\ProposedSiteOrLandProposed;
use App\Models\Property;



class LandService
{
    public function create(array $data)
    {

        $location = Location::create([
            'coordinate_link' => null, 
        ]);

        if (isset($data['file']) && $data['file'] instanceof \Illuminate\Http\UploadedFile) {
            \Log::info('Traitement KML', [
                'name' => $data['file']->getClientOriginalName(),
                'extension' => $data['file']->getClientOriginalExtension(),
            ]);
            
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
                @unlink($tempFilePath);
            }
            } else {
                \Log::warning('Pas de fichier KML ou mauvais type', [
                    'isset' => isset($data['file']),
                    'type' => isset($data['file']) ? gettype($data['file']) : 'not_set'
                ]);
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

        if (isset($data['images']) && is_array($data['images'])) {
            
            foreach ($data['images'] as $index => $image) {
                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $media = $land->addMedia($image)->toMediaCollection('land');
                }
            }
        } else {
            \Log::warning('Pas d\'images ou pas un tableau', [
                'isset' => isset($data['images']),
                'type' => isset($data['images']) ? gettype($data['images']) : 'not_set'
            ]);
        }

        if (isset($data['fragments'])) {
            collect($data['fragments'])->each(function ($fragment) use ($land) {
                $land->fragments()->create(['area' => (float) $fragment]);
            });
        }

         if (isset($data['videoLink']) && trim($data['videoLink']) !== '') {
            try {
                $video = $land->videoLands()->create([
                    'videoLink' => trim($data['videoLink'])
                ]);
            } catch (\Exception $e) {
                \Log::error('Erreur création video_lands', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (isset($data['proposed_property_ids']) && is_array($data['proposed_property_ids'])) {
            collect($data['proposed_property_ids'])->each(function ($propertyId) use ($land) {
                ProposedSiteOrLandProposed::create([
                    'land_id' => $land->id,            
                    'proposable_id' => $propertyId,
                    'proposable_type' => Property::class,
                ]);
            });
        }

        $land->load([
            'fragments',
            'videoLands',
            'location.media',
            'location.address',
            'proposedSites.proposable' => function ($query) {
                $query->without(['accommodations', 'retail_spaces', 'proposedSites']);
            },
        ]);

        $land->refresh();
        $landImages = $land->getMedia('land');
        $location->refresh();
        $locationKml = $location->getMedia('kml');
        return $land;
    }

    private function simplifyAndSaveKml(string $originalPath, string $originalFileName): string
    {
        $xml = @simplexml_load_file($originalPath); 

        if (!$xml) {
            throw new Exception("Impossible de charger le KML. Vérifiez le format XML.");
        }
        
        $tempFilePath = tempnam(sys_get_temp_dir(), 'kml_min_'); 
        
        if ($tempFilePath === false) {
            throw new Exception("Impossible de créer un fichier temporaire pour le KML simplifié.");
        }
        if (isset($xml->Document->Schema)) {
            unset($xml->Document->Schema);
        }
        $placemarks = $xml->xpath('//Placemark');
        foreach ($placemarks as $placemark) {
            if (isset($placemark->ExtendedData)) {
                unset($placemark->ExtendedData); 
            }
        }
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
