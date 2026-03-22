<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $offset = ($page - 1) * $perPage;
        
        $query = Designation::query();
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        $total = $query->count();
        $designations = $query->orderBy('name')->offset($offset)->limit($perPage)->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => $designations,
            'total' => $total,
            'page' => (int) $page,
            'per_page' => (int) $perPage,
            'has_more' => ($offset + count($designations)) < $total,
        ]);
    }
}
