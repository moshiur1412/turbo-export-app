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
        
        $query = Designation::query();
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        $designations = $query->orderBy('name')->limit(50)->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => $designations,
        ]);
    }
}
