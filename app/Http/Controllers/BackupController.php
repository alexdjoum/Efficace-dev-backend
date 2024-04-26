<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des backups',
            'data' => Backup::all()
        ]);
    }

    public function download(Backup $backup)
    {
        return response()->download(storage_path('app/' . $backup->path));
    }

    public function destroy(Backup $backup)
    {
        Storage::disk("local")->delete($backup->path);

        $backup->delete();
        return response()->json([
            'success' => true,
            'message' => 'Backup supprimé',
            'data' => null
        ]);
    }
}