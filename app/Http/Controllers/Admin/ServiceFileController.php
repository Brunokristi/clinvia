<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceFileController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $uploadedFile = $request->file('file');

        $path = $uploadedFile->store('service-files', 'public');

        $service->files()->create([
            'label' => $data['label'] ?? null,
            'file_path' => $path,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Súbor bol nahratý.');
    }

    public function destroy(Request $request, Service $service, ServiceFile $file): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($service->company_id), 403);
        abort_if($file->service_id !== $service->id, 404);

        if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();

        return back()->with('success', 'Súbor bol odstránený.');
    }
}