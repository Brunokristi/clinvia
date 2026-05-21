<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceNecessity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceNecessityController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);

        $data = $request->validate([
            'text' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $service->necessities()->create([
            'text' => $data['text'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Položka bola pridaná.');
    }

    public function destroy(Request $request, Service $service, ServiceNecessity $necessity): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);
        abort_if($necessity->service_id !== $service->id, 404);

        $necessity->delete();

        return back()->with('success', 'Položka bola odstránená.');
    }
}