<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceInformation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceInformationController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);

        $data = $request->validate([
            'text' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $service->information()->create([
            'text' => $data['text'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Informácia bola pridaná.');
    }

    public function destroy(Request $request, Service $service, ServiceInformation $information): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);
        abort_if($information->service_id !== $service->id, 404);

        $information->delete();

        return back()->with('success', 'Informácia bola odstránená.');
    }
}