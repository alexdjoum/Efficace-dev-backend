<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => "Liste des employés",
            'data' => Employee::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, EmployeeService $employeeService)
    {
        $validator = validator()->make($request->all(), [
            "first_name" => "required|string",
            "last_name" => "required|string",
            "email" => "required|email|unique:users",
            "phone" => "required|string",
            "position" => "required|string",
            "password" => "required|string|min:6|confirmed",
            "roles" => "array",
            "roles.*" => "required|integer|exists:roles,id",
            "permissions" => "array",
            "permissions.*" => "required|integer|exists:permissions,id",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => "Erreurs de validation.",
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $request->merge([
            "email_verified_at" => now(),
        ]);

        $employee = DB::transaction(function () use ($request, $employeeService) {
            return $employeeService->create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => "Employé crée avec succès.",
            'data' => $employee
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        return response()->json([
            'success' => true,
            'message' => "Employé",
            'data' => $employee
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee, EmployeeService $employeeService)
    {
        $validator = validator()->make($request->all(), [
            "first_name" => "sometimes|required|string",
            "last_name" => "sometimes|required|string",
            "email" => "sometimes|required|email|unique:users,email," . $employee->id,
            "phone" => "sometimes|required|string",
            "position" => "sometimes|required|string",
            "roles" => "array",
            "roles.*" => "sometimes|required|integer|exists:roles,id",
            "permissions" => "array",
            "permissions.*" => "sometimes|required|integer|exists:permissions,id",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => "Erreurs de validation.",
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $employee = DB::transaction(function () use ($request, $employee, $employeeService) {
            return $employeeService->update($employee, $request->all());
        });

        return response()->json([
            'success' => true,
            'message' => "Employé mis à jour avec succès.",
            'data' => $employee
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => "Employé supprimé avec succès.",
            'data' => null
        ], 204);
    }
}
