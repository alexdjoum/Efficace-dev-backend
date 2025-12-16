<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $basePath = public_path('storage/models');

        if (!is_dir($basePath)) {
            return;
        }

        $insertData = [];

        foreach (scandir($basePath) as $folder) {

            if ($folder === '.' || $folder === '..') {
                continue;
            }

            $modelId = intval($folder);

            if ($modelId === 0) {
                continue;
            }

            $folderPath = $basePath . '/' . $folder;

            if (!is_dir($folderPath)) {
                continue;
            }

            $files = array_values(array_diff(scandir($folderPath), ['.', '..']));

            if (count($files) === 0) {
                continue;
            }

            $file = $files[0]; // un seul fichier par dossier
            $filePath = $folderPath . '/' . $file;

            if (!is_file($filePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => null,
            };

            if (!$mime) continue;

            $insertData[] = [
                'model_type' => 'Land',
                'model_id' => $modelId,
                'uuid' => Str::uuid(),
                'collection_name' => 'land',
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'file_name' => $file,
                'mime_type' => $mime,
                'disk' => 'public',
                'conversions_disk' => null,
                'size' => filesize($filePath) ?: 0,
                'manipulations' => json_encode([]),
                'custom_properties' => json_encode([]),
                'generated_conversions' => json_encode([]),
                'responsive_images' => json_encode([]),
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($insertData)) {
            DB::table('media')->insert($insertData);
        }
    }
}
