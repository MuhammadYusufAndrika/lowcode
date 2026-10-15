<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    public function save(?Department $department, array $data): void
    {
        DB::transaction(function () use ($department, $data) {
            if ($department) {
                Department::lockForUpdate()->findOrFail($department->id)->update($data);
            } else {
                Department::create($data);
            }
        });
    }

    public function delete(Department $department): void
    {
        DB::transaction(fn () => Department::lockForUpdate()->findOrFail($department->id)->delete());
    }
}
