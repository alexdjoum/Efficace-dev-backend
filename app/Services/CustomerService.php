<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(public readonly UserService $userService)
    {
    }
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. Créer le customer SANS charger les relations (withoutRelations)
            $customer = Customer::withoutEvents(function () use ($data) {
                return Customer::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'],
                    'type' => $data['type'] ?? null,
                ]);
            });

            // 2. Créer l'utilisateur associé
            $user = User::create([
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'userable_type' => Customer::class,
                'userable_id' => $customer->id,
            ]);

            // 3. Créer l'adresse si fournie
            if (!empty($data['address'])) {
                Address::create([
                    'addressable_type' => Customer::class,
                    'addressable_id' => $customer->id,
                    'address' => $data['address'],
                    // Ajoutez d'autres champs selon votre table addresses
                ]);
            }

            // 4. Recharger le customer avec toutes ses relations
            $customer = $customer->fresh(['user', 'address']);

            return $customer;
        });
    }

    public function update(Customer $customer, array $data)
    {
        $customer->update($data);

        if (isset($data['country'])) {
            $address = $customer->address;
            if($address) {
                $address->update($data);
            } else {
                $address = Address::make($data);

                $customer->address()->save($address);
            }

        }

        $this->userService->update($customer->user, $data);

        return $customer->fresh();
    }
}
