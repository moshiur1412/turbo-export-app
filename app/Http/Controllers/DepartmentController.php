<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::orderBy('name')->get(['id', 'name', 'code', 'location']);

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }
}
