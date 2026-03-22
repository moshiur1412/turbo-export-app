<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $offset = ($page - 1) * $perPage;
        
        $query = Department::query();
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        $total = $query->count();
        $departments = $query->orderBy('name')->offset($offset)->limit($perPage)->get(['id', 'name', 'code', 'location']);

        return response()->json([
            'success' => true,
            'data' => $departments,
            'total' => $total,
            'page' => (int) $page,
            'per_page' => (int) $perPage,
            'has_more' => ($offset + count($departments)) < $total,
        ]);
    }
}
