<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;

class TransactionCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = TransactionCategory::query()->where('status', 1);

        if ($request->filled('type')) {
            $query->where('type', (int) $request->get('type'));
        }

        $categories = $query->orderBy('name')->get(['id', 'name', 'type', 'status']);

        return $this->returnSuccess(200, $categories);
    }
}
