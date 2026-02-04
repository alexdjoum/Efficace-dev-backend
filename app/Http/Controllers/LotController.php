<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use Illuminate\Http\Request;

class LotController extends Controller
{
    public function index()
    {
        $lots = Lot::all();

        return response()->json([
            'success' => true,
            'data' => $lots
        ]);
    }
}