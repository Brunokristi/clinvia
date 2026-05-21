<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceStepController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);

        $data = $request->validate([
            'number' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $service->steps()->create([
            'number' => $data['number'] ?? null,
            'title' => $data['title'] ?? null,
            'text' => $data['text'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Krok bol pridaný.');
    }

    public function destroy(Request $request, Service $service, ServiceStep $step): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);
        abort_if($step->service_id !== $service->id, 404);

        $step->delete();

        return back()->with('success', 'Krok bol odstránený.');
    }
}