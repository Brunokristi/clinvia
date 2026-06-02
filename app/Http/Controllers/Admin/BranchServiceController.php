<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchServiceController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'new_category_name' => ['nullable', 'string', 'max:255'],

            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'duration_sessions' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_bookable' => ['required', 'boolean'],

            'is_available' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],

            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_note' => ['nullable', 'string'],
            'self_pay_amount' => ['nullable', 'numeric', 'min:0'],
            'self_pay_note' => ['nullable', 'string'],

            'information' => ['nullable', 'array'],
            'information.*.existing_id' => ['nullable', 'integer'],
            'information.*.text' => ['nullable', 'string'],
            'information.*.sort_order' => ['nullable', 'integer'],
            'information.*.is_active' => ['nullable', 'boolean'],

            'steps' => ['nullable', 'array'],
            'steps.*.existing_id' => ['nullable', 'integer'],
            'steps.*.number' => ['nullable', 'integer'],
            'steps.*.title' => ['nullable', 'string', 'max:255'],
            'steps.*.text' => ['nullable', 'string'],
            'steps.*.sort_order' => ['nullable', 'integer'],
            'steps.*.is_active' => ['nullable', 'boolean'],

            'files' => ['nullable', 'array'],
            'files.*.existing_id' => ['nullable', 'integer'],
            'files.*.label' => ['nullable', 'string', 'max:255'],
            'files.*.file' => ['nullable', 'file', 'max:10240'],
            'files.*.sort_order' => ['nullable', 'integer'],
            'files.*.is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($data['category_id']) && empty($data['new_category_name'])) {
            throw ValidationException::withMessages([
                'new_category_name' => ['Pri vytváraní novej kategórie je potrebný názov.'],
            ]);
        }

        DB::transaction(function () use ($data, $branch, $request): void {
            $categoryId = $this->resolveCategoryId($branch, $data);

            $baseSlug = ! empty($data['slug'])
                ? Str::slug($data['slug'])
                : Str::slug($data['name']);

            if (empty($baseSlug)) {
                $baseSlug = Str::random(8);
            }

            $slug = $this->uniqueServiceSlug($branch, $baseSlug);

            $service = Service::create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'category_id' => $categoryId,
                'name' => $data['name'],
                'slug' => $slug,
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'duration_sessions' => $data['duration_sessions'] ?? 1,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'is_bookable' => $data['is_bookable'] ?? false,

                'capacity' => 1,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'booking_type' => 'individual',

                'insurance_amount' => $data['insurance_amount'] ?? null,
                'insurance_note' => $data['insurance_note'] ?? null,
                'self_pay_amount' => $data['self_pay_amount'] ?? null,
                'self_pay_note' => $data['self_pay_note'] ?? null,
                'is_active' => $data['is_available'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncServiceExtras($service, $data, $request);
        });

        return back()->with('success', 'Služba bola vytvorená pre pobočku.');
    }

    public function update(Request $request, Branch $branch, Service $branchService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchService->branch_id !== $branch->id, 404);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'new_category_name' => ['nullable', 'string', 'max:255'],

            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'duration_sessions' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_bookable' => ['required', 'boolean'],

            'is_available' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],

            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_note' => ['nullable', 'string'],
            'self_pay_amount' => ['nullable', 'numeric', 'min:0'],
            'self_pay_note' => ['nullable', 'string'],

            'information' => ['nullable', 'array'],
            'information.*.existing_id' => ['nullable', 'integer'],
            'information.*.text' => ['nullable', 'string'],
            'information.*.sort_order' => ['nullable', 'integer'],
            'information.*.is_active' => ['nullable', 'boolean'],

            'steps' => ['nullable', 'array'],
            'steps.*.existing_id' => ['nullable', 'integer'],
            'steps.*.number' => ['nullable', 'integer'],
            'steps.*.title' => ['nullable', 'string', 'max:255'],
            'steps.*.text' => ['nullable', 'string'],
            'steps.*.sort_order' => ['nullable', 'integer'],
            'steps.*.is_active' => ['nullable', 'boolean'],

            'files' => ['nullable', 'array'],
            'files.*.existing_id' => ['nullable', 'integer'],
            'files.*.label' => ['nullable', 'string', 'max:255'],
            'files.*.file' => ['nullable', 'file', 'max:10240'],
            'files.*.sort_order' => ['nullable', 'integer'],
            'files.*.is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($data['category_id']) && empty($data['new_category_name'])) {
            throw ValidationException::withMessages([
                'new_category_name' => ['Pri vytváraní novej kategórie je potrebný názov.'],
            ]);
        }

        DB::transaction(function () use ($data, $branch, $branchService, $request): void {
            $categoryId = $this->resolveCategoryId($branch, $data);

            $branchService->update([
                'category_id' => $categoryId,
                'name' => $data['name'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'duration_sessions' => $data['duration_sessions'] ?? 1,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'is_bookable' => $data['is_bookable'] ?? false,

                'capacity' => 1,
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'booking_type' => 'individual',

                'is_active' => $data['is_available'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
                'insurance_amount' => $data['insurance_amount'] ?? null,
                'insurance_note' => $data['insurance_note'] ?? null,
                'self_pay_amount' => $data['self_pay_amount'] ?? null,
                'self_pay_note' => $data['self_pay_note'] ?? null,
            ]);

            $this->syncServiceExtras($branchService, $data, $request);
        });

        return back()->with('success', 'Služba pobočky bola upravená.');
    }

    public function destroy(Request $request, Branch $branch, Service $branchService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchService->branch_id !== $branch->id, 404);

        $branchService->delete();

        return back()->with('success', 'Služba bola odstránená z pobočky.');
    }

    private function resolveCategoryId(Branch $branch, array $data): int
    {
        $categoryId = $data['category_id'] ?? null;

        if (! empty($categoryId)) {
            return (int) $categoryId;
        }

        $baseSlug = Str::slug($data['new_category_name']);
        $slug = $baseSlug;
        $counter = 1;

        while (Category::where('company_id', $branch->company_id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $category = Category::create([
            'company_id' => $branch->company_id,
            'name' => $data['new_category_name'],
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return $category->id;
    }

    private function uniqueServiceSlug(Branch $branch, string $baseSlug, ?Service $ignoreService = null): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (
            Service::where('branch_id', $branch->id)
                ->where('slug', $slug)
                ->when($ignoreService, function ($query) use ($ignoreService) {
                    $query->whereKeyNot($ignoreService->id);
                })
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function syncServiceExtras(Service $service, array $data, Request $request): void
    {
        $information = $this->arrayItems($data['information'] ?? []);
        $steps = $this->arrayItems($data['steps'] ?? []);
        $files = $this->arrayItems($data['files'] ?? []);

        $this->syncInformation($service, $information);
        $this->syncSteps($service, $steps);
        $this->syncFiles($service, $files, $request);
    }

    private function arrayItems(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, function ($item) {
            return is_array($item);
        }));
    }

    private function syncInformation(Service $service, array $items): void
    {
        $existingIds = [];

        foreach ($items as $index => $item) {
            if (empty(trim((string) ($item['text'] ?? '')))) {
                continue;
            }

            $payload = [
                'text' => $item['text'],
                'sort_order' => $item['sort_order'] ?? $index,
                'is_active' => $item['is_active'] ?? true,
            ];

            if (! empty($item['existing_id'])) {
                $information = $service->information()->whereKey($item['existing_id'])->first();

                if ($information) {
                    $information->update($payload);
                    $existingIds[] = $information->id;
                    continue;
                }
            }

            $information = $service->information()->create($payload);
            $existingIds[] = $information->id;
        }

        $service->information()
            ->whereNotIn('id', $existingIds ?: [0])
            ->delete();
    }

    private function syncSteps(Service $service, array $items): void
    {
        $existingIds = [];

        foreach ($items as $index => $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));

            if ($title === '' && $text === '') {
                continue;
            }

            $payload = [
                'number' => $item['number'] ?? ($index + 1),
                'title' => $title !== '' ? $title : null,
                'text' => $text !== '' ? $text : null,
                'sort_order' => $item['sort_order'] ?? $index,
                'is_active' => $item['is_active'] ?? true,
            ];

            if (! empty($item['existing_id'])) {
                $step = $service->steps()->whereKey($item['existing_id'])->first();

                if ($step) {
                    $step->update($payload);
                    $existingIds[] = $step->id;
                    continue;
                }
            }

            $step = $service->steps()->create($payload);
            $existingIds[] = $step->id;
        }

        $service->steps()
            ->whereNotIn('id', $existingIds ?: [0])
            ->delete();
    }

    private function syncFiles(Service $service, array $items, Request $request): void
    {
        $existingIds = [];
        $uploadedFiles = $request->file('files', []);

        if (! is_array($uploadedFiles)) {
            $uploadedFiles = [];
        }

        foreach ($items as $index => $item) {
            $uploadedFile = $uploadedFiles[$index]['file'] ?? null;
            $existingId = $item['existing_id'] ?? null;

            if (! $uploadedFile && empty($existingId)) {
                continue;
            }

            if (! empty($existingId)) {
                $fileRecord = $service->files()->whereKey($existingId)->first();

                if (! $fileRecord) {
                    continue;
                }

                if ($uploadedFile) {
                    if ($fileRecord->file_path && Storage::disk('public')->exists($fileRecord->file_path)) {
                        Storage::disk('public')->delete($fileRecord->file_path);
                    }

                    $path = $uploadedFile->store('service-files', 'public');

                    $fileRecord->update([
                        'label' => $item['label'] ?? null,
                        'file_path' => $path,
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'mime_type' => $uploadedFile->getClientMimeType(),
                        'size' => $uploadedFile->getSize(),
                        'sort_order' => $item['sort_order'] ?? $index,
                        'is_active' => $item['is_active'] ?? true,
                    ]);
                } else {
                    $fileRecord->update([
                        'label' => $item['label'] ?? null,
                        'sort_order' => $item['sort_order'] ?? $index,
                        'is_active' => $item['is_active'] ?? true,
                    ]);
                }

                $existingIds[] = $fileRecord->id;
                continue;
            }

            if (! $uploadedFile) {
                continue;
            }

            $path = $uploadedFile->store('service-files', 'public');

            $fileRecord = $service->files()->create([
                'label' => $item['label'] ?? null,
                'file_path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'sort_order' => $item['sort_order'] ?? $index,
                'is_active' => $item['is_active'] ?? true,
            ]);

            $existingIds[] = $fileRecord->id;
        }

        $service->files()
            ->whereNotIn('id', $existingIds ?: [0])
            ->get()
            ->each(function (ServiceFile $file): void {
                if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
                    Storage::disk('public')->delete($file->file_path);
                }

                $file->delete();
            });
    }
}