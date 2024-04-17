<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des roles',
            'data' => Role::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'name' => 'required|unique:roles',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ]);
        }

        $role = Role::create([
            'name' => $request->name
        ]);

        $role->syncPermissions($request->permissions);

        activity()->performedOn($role)->log('Ce model a été crée');

        return response()->json([
            'success' => true,
            'message' => 'Role crée avec succès',
            'data' => $role
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        return response()->json([
            'success' => true,
            'message' => 'Details du role',
            'data' => $role
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $validator = validator()->make($request->all(), [
            'name' => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'data' => ['errors' => $validator->errors()]
            ]);
        }

        $role->update([
            'name' => $request->name
        ]);

        $role->syncPermissions($request->permissions);

        $role->refresh();

        activity()->performedOn($role)->log('Ce model a été modifié');

        return response()->json([
            'success' => true,
            'message' => 'Role mis à jour avec succès',
            'data' => $role
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $role->delete();
        activity()->performedOn($role)->log('Ce model a été supprimé');
        return response()->json([
            'success' => true,
            'message' => 'Role supprimé avec succès.',
            'data' => null
        ]);
    }

    // list of permissions
    public function permissions()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des permissions',
            'data' => Permission::all()
        ]);
    }
}
