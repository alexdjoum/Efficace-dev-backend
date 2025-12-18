<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Vider les tables pivot existantes
        DB::table('role_permission')->truncate();
        DB::table('role_has_permissions')->truncate();
        
        if (Schema::hasTable('role_user')) {
            DB::table('role_user')->truncate();
        }
        
        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')->truncate();
        }
        
        // Vider les tables principales
        Permission::query()->delete();
        Role::query()->delete();

        // Créer les rôles avec hiérarchie
        $admin = Role::create([
            'name' => 'Administrateur',
            'slug' => 'admin',
            'guard_name' => 'api',
            'hierarchy_level' => 5,
            'description' => 'Administrateur avec tous les droits'
        ]);

        $validator = Role::create([
            'name' => 'Validateur',
            'slug' => 'validator',
            'guard_name' => 'api',
            'hierarchy_level' => 4,
            'description' => 'Valide les corrections'
        ]);

        $corrector = Role::create([
            'name' => 'Correcteur',
            'slug' => 'corrector',
            'guard_name' => 'api',
            'hierarchy_level' => 3,
            'description' => 'Effectue les corrections'
        ]);

        $manager = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'guard_name' => 'api',
            'hierarchy_level' => 2,
            'description' => 'Gère un aspect spécifique du projet'
        ]);

        // ✅ Nouveau rôle Customer (niveau le plus faible)
        $customer = Role::create([
            'name' => 'Client',
            'slug' => 'customer',
            'guard_name' => 'api',
            'hierarchy_level' => 1,
            'description' => 'Client standard avec permissions limitées'
        ]);

        // Créer les permissions
        $permissions = [
            // Permissions de correction
            ['name' => 'Créer correction', 'slug' => 'create-correction', 'guard_name' => 'api', 'description' => null],
            ['name' => 'Modifier correction', 'slug' => 'edit-correction', 'guard_name' => 'api', 'description' => null],
            ['name' => 'Supprimer correction', 'slug' => 'delete-correction', 'guard_name' => 'api', 'description' => null],
            ['name' => 'Voir corrections', 'slug' => 'view-corrections', 'guard_name' => 'api', 'description' => null],
            
            // Permissions de validation
            ['name' => 'Valider correction', 'slug' => 'validate-correction', 'guard_name' => 'api', 'description' => null],
            ['name' => 'Rejeter correction', 'slug' => 'reject-correction', 'guard_name' => 'api', 'description' => null],
            
            // Permissions de gestion
            ['name' => 'Gérer utilisateurs', 'slug' => 'manage-users', 'guard_name' => 'api', 'description' => null],
            ['name' => 'Gérer rôles', 'slug' => 'manage-roles', 'guard_name' => 'api', 'description' => null],
            ['name' => 'Voir rapports', 'slug' => 'view-reports', 'guard_name' => 'api', 'description' => null],
            
            // Permissions Manager
            ['name' => 'Gérer ressources', 'slug' => 'manage-resources', 'guard_name' => 'api', 'description' => null],
            ['name' => 'Voir statistiques', 'slug' => 'view-statistics', 'guard_name' => 'api', 'description' => null],
            
            // ✅ Permissions Customer (limitées)
            ['name' => 'Voir son profil', 'slug' => 'view-own-profile', 'guard_name' => 'api', 'description' => 'Voir son propre profil'],
            ['name' => 'Modifier son profil', 'slug' => 'edit-own-profile', 'guard_name' => 'api', 'description' => 'Modifier son propre profil'],
            ['name' => 'Voir ses commandes', 'slug' => 'view-own-orders', 'guard_name' => 'api', 'description' => 'Voir ses propres commandes'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Assigner permissions aux rôles
        
        // Corrector
        $corrector->permissions()->sync(
            Permission::whereIn('slug', [
                'create-correction',
                'edit-correction',
                'view-corrections',
            ])->pluck('id')
        );

        // Validator (hérite de Corrector + ses propres permissions)
        $validator->permissions()->sync(
            Permission::whereIn('slug', [
                'create-correction',
                'edit-correction',
                'view-corrections',
                'validate-correction',
                'reject-correction',
                'view-reports',
            ])->pluck('id')
        );

        // Manager
        $manager->permissions()->sync(
            Permission::whereIn('slug', [
                'manage-resources',
                'view-statistics',
                'view-reports',
            ])->pluck('id')
        );

        // ✅ Customer (permissions limitées)
        $customer->permissions()->sync(
            Permission::whereIn('slug', [
                'view-own-profile',
                'edit-own-profile',
                'view-own-orders',
            ])->pluck('id')
        );

        // Admin a toutes les permissions
        $admin->permissions()->sync(Permission::all()->pluck('id'));
        
        $this->command->info('✅ Rôles et permissions créés avec succès!');
        $this->command->info('   - Admin (niveau 5) : ' . $admin->permissions->count() . ' permissions');
        $this->command->info('   - Validator (niveau 4) : ' . $validator->permissions->count() . ' permissions');
        $this->command->info('   - Corrector (niveau 3) : ' . $corrector->permissions->count() . ' permissions');
        $this->command->info('   - Manager (niveau 2) : ' . $manager->permissions->count() . ' permissions');
        $this->command->info('   - Customer (niveau 1) : ' . $customer->permissions->count() . ' permissions');
    }
}