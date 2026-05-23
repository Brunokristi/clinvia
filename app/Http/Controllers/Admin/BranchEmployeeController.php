<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BranchEmployeeController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'create_new' => ['required', 'boolean'],

            'first_name' => ['nullable', 'string', 'max:255', 'required_if:create_new,true'],
            'last_name' => ['nullable', 'string', 'max:255', 'required_if:create_new,true'],
            'title_before' => ['nullable', 'string', 'max:255'],
            'title_after' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:4096'],

            'role' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if ($data['create_new']) {
            $baseSlug = Str::slug($data['first_name'] . ' ' . $data['last_name']);
            $slug = $baseSlug;
            $counter = 1;

            while (Employee::where('company_id', $branch->company_id)->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $photoPath = null;

            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('employees', 'public');
            }

            $employee = Employee::create([
                'company_id' => $branch->company_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'slug' => $slug,
                'title_before' => $data['title_before'] ?? null,
                'title_after' => $data['title_after'] ?? null,
                'position' => $data['position'] ?? null,
                'bio' => $data['bio'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'sort_order' => 0,
                'photo_path' => $photoPath,
            ]);
        } else {
            $employee = Employee::findOrFail($data['employee_id']);

            abort_if($employee->company_id !== $branch->company_id, 403);
        }

        $branch->employees()->syncWithoutDetaching([
            $employee->id => [
                'role' => $data['role'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ],
        ]);

        return back()->with('success', 'Zamestnanec bol pridaný k pobočke.');
    }

    public function update(Request $request, Branch $branch, Employee $employee): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        abort_if($employee->company_id !== $branch->company_id, 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'title_before' => ['nullable', 'string', 'max:255'],
            'title_after' => ['nullable', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $photoPath = $employee->photo_path;

        if ($request->hasFile('photo')) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            $photoPath = $request->file('photo')->store('employees', 'public');
        }

        $employee->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'title_before' => $data['title_before'] ?? null,
            'title_after' => $data['title_after'] ?? null,
            'position' => $data['position'],
            'bio' => $data['bio'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'photo_path' => $photoPath,
        ]);

        return back()->with('success', 'Zamestnanec bol upravený.');
    }

    public function destroy(Request $request, Branch $branch, Employee $employee): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $branch->employees()->detach($employee->id);

        return back()->with('success', 'Zamestnanec bol odstránený z pobočky.');
    }
}