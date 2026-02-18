<?php

namespace App\Http\Controllers;

use App\Models\WorkerAvailability;
use Illuminate\Http\Request;
use Carbon\Carbon;

class WorkerAvailabilityController extends Controller
{

    public function store(Request $request)
    {
        $user = auth()->user();

        $maxEndDate = Carbon::now()->addDays(15)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');

        $validated = $request->validate([
            'start_date' => 'required|date|after_or_equal:' . $today,
            'end_date' => 'required|date|after:start_date|before_or_equal:' . $maxEndDate,
        ], [
            'start_date.after_or_equal' => 'La date de début doit être à partir d\'aujourd\'hui (' . $today . ')',
            'end_date.after' => 'La date de fin doit être après la date de début',
            'end_date.before_or_equal' => 'La date de fin doit être au plus tard dans 15 jours (' . $maxEndDate . ')',
        ]);

        $overlap = WorkerAvailability::where('user_id', $user->id)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($query) use ($validated) {
                        $query->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            })->first();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Cette période chevauche une disponibilité existante',
            ], 422);
        }

        $availability = WorkerAvailability::create([
            'user_id' => $user->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilité créée avec succès',
            'data' => $availability
        ], 201);
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2024',
        ]);

        $query = WorkerAvailability::where('user_id', $user->id);

        if (!empty($validated['month']) && !empty($validated['year'])) {
            $query->whereMonth('start_date', $validated['month'])
                ->whereYear('start_date', $validated['year']);

        } elseif (!empty($validated['month'])) {
            $query->whereMonth('start_date', $validated['month']);

        } elseif (!empty($validated['year'])) {
            $query->whereYear('start_date', $validated['year']);
        }

        $availabilities = $query->orderBy('start_date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $availabilities
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $availability = WorkerAvailability::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($availability->end_date->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier une disponibilité déjà expirée',
            ], 422);
        }

        $minDate = Carbon::now()->addDays(15)->format('Y-m-d');

        $validated = $request->validate([
            'start_date' => 'sometimes|date|after_or_equal:' . $minDate,
            'end_date' => 'sometimes|date|after:start_date|after:today',
        ], [
            'start_date.after_or_equal' => 'La date de début doit être au minimum dans 15 jours (' . $minDate . ')',
            'end_date.after' => 'La date de fin doit être après la date de début',
        ]);

        $overlap = WorkerAvailability::where('user_id', $user->id)
            ->where('id', '!=', $id)
            ->where(function ($query) use ($validated, $availability) {
                $start = $validated['start_date'] ?? $availability->start_date;
                $end = $validated['end_date'] ?? $availability->end_date;
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })->first();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Cette période chevauche une disponibilité existante',
            ], 422);
        }

        $availability->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilité mise à jour avec succès',
            'data' => $availability->fresh()
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $availability = WorkerAvailability::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($availability->end_date->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une disponibilité déjà expirée',
            ], 422);
        }

        $availability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Disponibilité supprimée avec succès',
        ]);
    }

    public function workerAvailabilities($userId)
    {
        $availabilities = WorkerAvailability::where('user_id', $userId)
            ->where('end_date', '>=', Carbon::now())
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $availabilities
        ]);
    }
}