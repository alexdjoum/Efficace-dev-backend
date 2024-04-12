<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use App\Models\Customer;

class CustomerService
{
    public function __construct(public readonly UserService $userService)
    {
    }
    public function create(array $data)
    {
        $customer = Customer::create($data);

        $this->userService->create($data, $customer);

        if (isset($data['country'])) {
            $address = Address::make($data);

            $customer->address()->save($address);
        }

        $customer->fresh();

        return $customer;
    }

    public function update(Customer $customer, array $data)
    {
        $customer->update($data);

        if (isset($data['country'])) {
            $address = $customer->address;

            $address->update($data);
        }

        $this->userService->update($customer->user, $data);
        $customer->fresh();
        return $customer;
    }
}
