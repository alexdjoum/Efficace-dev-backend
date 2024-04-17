<?php

namespace App\Services;

use App\Models\Employee;


class EmployeeService
{

    public function __construct(public readonly UserService $userService)
    {
    }
    public function create(array $data)
    {
        $employee = Employee::create($data);
        $this->userService->create($data, $employee);

        return $employee->fresh();
    }

    public function update(Employee $employee, array $data)
    {
        $employee->update($data);

        $this->userService->update($employee->user, $data);

        return $employee->fresh();
    }
}
