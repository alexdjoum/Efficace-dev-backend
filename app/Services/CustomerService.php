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
        $customer = Customer::withoutEvents(function () use ($data) {
            return Customer::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'type' => $data['type'] ?? null,
            ]);
        });

        $user = User::create([
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'userable_type' => Customer::class,
            'userable_id' => $customer->id,
        ]);

        if (isset($data['street']) || isset($data['city']) || isset($data['country']) || isset($data['address'])) {
            Address::create([
                'street' => $data['street'] ?? $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'addressable_type' => Customer::class,
                'addressable_id' => $customer->id,
            ]);
        }

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
