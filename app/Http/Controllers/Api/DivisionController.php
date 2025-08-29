<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\JsonResponse;

class DivisionController extends Controller
{
    public function index(): JsonResponse
    {
        $divisions = Division::select('id', 'name', 'description')->get();
        return response()->json([
            'data' => $divisions
        ]);
    }
}
