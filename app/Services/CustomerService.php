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

        return $customer->fresh();
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
