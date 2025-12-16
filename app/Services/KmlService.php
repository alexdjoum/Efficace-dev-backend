<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class KmlService
{
    /**
     * Simplifie un fichier KML pour Google Earth Web
     *
     * @param string $originalPath Chemin complet du KML original
     * @param string $simplifiedRelativePath Chemin relatif où sauvegarder le KML simplifié (ex: 'coordinates/simplified_123.kml')
     * @return string Chemin complet du fichier simplifié
     * @throws \Exception
     */
    public function simplifyKml(string $originalPath, string $simplifiedRelativePath): string
    {
        // Charger le fichier KML
        $xml = simplexml_load_file($originalPath);

        if (!$xml) {
            throw new \Exception("Impossible de charger le KML : $originalPath");
        }

        // 🔹 Supprimer <Schema> dans <Document>
        if (isset($xml->Document->Schema)) {
            unset($xml->Document->Schema);
        }

        // 🔹 Supprimer <ExtendedData> dans chaque Placemark
        if (isset($xml->Document->Folder->Placemark)) {
            foreach ($xml->Document->Folder->Placemark as $placemark) {
                if (isset($placemark->ExtendedData)) {
                    unset($placemark->ExtendedData);
                }
            }
        }

        // 📁 Chemin complet où sauvegarder le fichier simplifié
        $simplifiedFullPath = Storage::path($simplifiedRelativePath);

        // S'assurer que le dossier existe
        $dir = dirname($simplifiedFullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Sauvegarder le KML simplifié
        $xml->asXML($simplifiedFullPath);

        return $simplifiedFullPath;
    }
}
