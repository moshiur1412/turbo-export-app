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
        
        $query = User::query()->with('userDetails');
        
        $numericSearch = is_numeric($search) ? (int) $search : null;
        $searchLower = strtolower($search);
        
        if ($search) {
            $query->where(function($q) use ($search, $numericSearch, $searchLower) {
                if ($numericSearch !== null) {
                    $q->orWhere('id', $numericSearch);
                }
                $q->orWhereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"]);
                $q->orWhereRaw('LOWER(employee_id) LIKE ?', ["%{$searchLower}%"]);
                $q->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchLower}%"]);
                $q->orWhereHas('userDetails', function($q) use ($searchLower) {
                    $q->whereRaw('LOWER(COALESCE(gender, \'\')) LIKE ?', ["%{$searchLower}%"]);
                });
            });
        }
        
        if ($search && $numericSearch !== null) {
            $query->orderByRaw("
                CASE 
                    WHEN id = ? THEN 0
                    WHEN LOWER(name) LIKE ? THEN 1
                    WHEN LOWER(name) LIKE ? THEN 2
                    ELSE 3
                END ASC, name ASC
            ", [$numericSearch, $search . '%', '%' . $search . '%']);
        } elseif ($search) {
            $query->orderByRaw("
                CASE 
                    WHEN LOWER(name) LIKE ? THEN 0
                    WHEN LOWER(name) LIKE ? THEN 1
                    ELSE 2
                END ASC, name ASC
            ", [$search . '%', '%' . $search . '%']);
        } else {
            $query->orderBy('name');
        }
        
        $total = $query->count();
        $employees = $query->offset($offset)->limit($perPage)->get();

        $employees = $employees->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee_id,
                'email' => $user->email,
                'gender' => $user->userDetails?->gender,
            ];
        });

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
