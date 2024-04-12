<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;


class EmployeeService
{

    public function __construct(public readonly UserService $userService)
    {
    }
    public function create(array $data)
    {
        $employee = Employee::create($data);
        $this->userService->create($data, $employee);

        return $employee;
    }

    public function update(Employee $employee, array $data)
    {
        $employee->update($data);

        $this->userService->update($employee->user, $data);

        return $employee;
    }
}