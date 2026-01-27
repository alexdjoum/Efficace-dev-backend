<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\TranslationService;

class LandResource extends JsonResource
{
    protected $translator;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->translator = new TranslationService();
    }

    public function toArray($request)
    {
        $currentLocale = app()->getLocale();
        $shouldTranslate = $currentLocale !== 'fr';

        $data = [
            'id' => $this->id,
            'area' => $this->area,
            'is_fragmentable' => $this->is_fragmentable,
            'relief' => $this->relief,
            'relief_translated' => $shouldTranslate && $this->relief 
                ? $this->translator->translate($this->relief) 
                : $this->relief,
            'description' => $shouldTranslate && $this->description 
                ? $this->translator->translate($this->description) 
                : $this->description,
            'location_id' => $this->location_id,
            'certificat_of_ownership' => $this->certificat_of_ownership,
            'technical_doc' => $this->technical_doc,
            'land_title' => $shouldTranslate && $this->land_title 
                ? $this->translator->translate($this->land_title) 
                : $this->land_title,
            'images' => $this->getMedia('land')->map(fn($media) => $media->getUrl()),
            'kml_file' => $this->getKmlFile(),
            'location' => $this->getLocation(),
            'fragments' => $this->fragments,
            'video_lands' => $this->videoLands,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return $data;
    }

    protected function getKmlFile()
    {
        $kmlMedia = $this->getFirstMedia('kml');
        
        if (!$kmlMedia && $this->location) {
            $kmlMedia = $this->location->getFirstMedia('kml');
        }
        
        return $kmlMedia ? [
            'url' => $kmlMedia->getUrl(),
            'name' => $kmlMedia->file_name,
            'size' => $kmlMedia->size,
            'mime_type' => $kmlMedia->mime_type,
        ] : null;
    }

    protected function getLocation()
    {
        if (!$this->location) {
            return null;
        }

        return [
            'id' => $this->location->id,
            'coordinate_link' => $this->location->coordinate_link,
            'kml' => $this->location->kml,
            'address' => $this->location->address ? [
                'id' => $this->location->address->id,
                'street' => $this->location->address->street,
                'city' => $this->location->address->city,
                'country' => $this->location->address->country,
            ] : null,
        ];
    }
}