<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchInboxMessageController extends Controller
{
    public function index(Request $request, Branch $branch): Response
    {
        $branch->loadMissing('company');

        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $messages = $branch->inboxMessages()
            ->with('booking')
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($status === 'unread', function ($query) {
                $query->whereNull('read_at');
            })
            ->when($status === 'read', function ($query) {
                $query->whereNotNull('read_at');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Branches/Inbox/Index', [
            'company' => $branch->company,
            'branch' => $branch,
            'messages' => $messages,
            'filters' => [
                'type' => $type,
                'status' => $status,
            ],
        ]);
    }

    public function show(Branch $branch, BranchInboxMessage $message): Response
    {
        abort_unless($message->branch_id === $branch->id, 404);

        if (! $message->read_at) {
            $message->update([
                'read_at' => now(),
            ]);
        }

        return Inertia::render('Admin/Branches/Inbox/Show', [
            'branch' => $branch,
            'message' => $message->fresh('booking'),
        ]);
    }

    public function markAsRead(Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_unless($message->branch_id === $branch->id, 404);

        if (! $message->read_at) {
            $message->update([
                'read_at' => now(),
            ]);
        }

        return back()->with('success', 'Správa bola označená ako prečítaná.');
    }

    public function destroy(Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_unless($message->branch_id === $branch->id, 404);

        $message->delete();

        return redirect()
            ->route('branches.inbox.index', $branch)
            ->with('success', 'Správa bola odstránená.');
    }
}