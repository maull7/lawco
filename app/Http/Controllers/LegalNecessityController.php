<?php

namespace App\Http\Controllers;

use App\Models\LegalNecessity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalNecessityController extends Controller
{
    public function index(): View
    {
        $requests = LegalNecessity::latest()->paginate(15);

        return view('legal-necessities.index', compact('requests'));
    }

    public function create(): View
    {
        return view('legal-necessities.create');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'legal_activities' => ['nullable', 'string', 'max:255'],
            'status_company' => ['nullable', 'string', 'max:255'],
            'value_trx' => ['nullable', 'string', 'max:255'],
            'target_output' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        LegalNecessity::create($data);

        return response()->json(['message' => 'Kebutuhan hukum berhasil disimpan.']);
    }
}
