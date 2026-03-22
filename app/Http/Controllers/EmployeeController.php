<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $offset = ($page - 1) * $perPage;
        
        $query = User::query();
        $numericSearch = is_numeric($search) ? (int) $search : null;
        
        if ($search) {
            $query->where(function($q) use ($search, $numericSearch) {
                if ($numericSearch !== null) {
                    $q->orWhere('id', $numericSearch);
                }
                $q->orWhere('name', 'like', "%{$search}%");
                $q->orWhere('employee_id', 'like', "%{$search}%");
                $q->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        if ($search && $numericSearch !== null) {
            $query->orderByRaw("
                CASE 
                    WHEN id = ? THEN 0
                    WHEN name LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END ASC, name ASC
            ", [$numericSearch, $search . '%', '%' . $search . '%']);
        } elseif ($search) {
            $query->orderByRaw("
                CASE 
                    WHEN name LIKE ? THEN 0
                    WHEN name LIKE ? THEN 1
                    ELSE 2
                END ASC, name ASC
            ", [$search . '%', '%' . $search . '%']);
        } else {
            $query->orderBy('name');
        }
        
        $total = $query->count();
        $employees = $query->offset($offset)->limit($perPage)->get(['id', 'name', 'employee_id', 'email']);

        return response()->json([
            'success' => true,
            'data' => $employees,
            'total' => $total,
            'page' => (int) $page,
            'per_page' => (int) $perPage,
            'has_more' => ($offset + count($employees)) < $total,
        ]);
    }
}
