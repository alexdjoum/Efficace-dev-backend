<?php

namespace App\Http\Controllers;

use App\Models\LocalisationWorker;

class LocalisationWorkerController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => LocalisationWorker::all()
        ]);
    }
}