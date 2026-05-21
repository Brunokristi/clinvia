<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceTagController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $service->tags()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Tag bol pridaný.');
    }

    public function destroy(Request $request, Service $service, ServiceTag $tag): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);
        abort_if($tag->service_id !== $service->id, 404);

        $tag->delete();

        return back()->with('success', 'Tag bol odstránený.');
    }
}