<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Notifications\ContactReplyNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use App\Events\BranchInboxUpdated;

class BranchInboxMessageController extends Controller
{
    public function index(Request $request, Branch $branch): Response
    {
        $branch->loadMissing('company');

        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $perPage = $request->integer('per_page', 15);

        if (! in_array($perPage, [10, 15, 20, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $messages = $branch->inboxMessages()
            ->with([
                'booking',
                'appointmentRequest',
            ])
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
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Branches/Inbox/Index', [
            'company' => $branch->company,
            'branch' => $branch,
            'messages' => $messages,
            'filters' => [
                'type' => $type,
                'status' => $status,
                'per_page' => $perPage,
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
            'message' => $message->fresh([
                'booking.bookingSlot',
                'booking.service',
                'booking.services',
                'appointmentRequest.services',
                'replies' => function ($query) {
                    $query->oldest();
                },
            ]),
            'replyTemplates' => $branch->replyTemplates()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function markAsRead(Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_unless($message->branch_id === $branch->id, 404);

        if (! $message->read_at) {
            $message->update([
                'read_at' => now(),
            ]);

            BranchInboxUpdated::dispatch(
                branchId: $branch->id,
                messageId: $message->id,
                action: 'read',
            );
        }

        return back()->with('success', 'Správa bola označená ako prečítaná.');
    }

    public function markAsUnread(Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_unless($message->branch_id === $branch->id, 404);

        if ($message->read_at) {
            $message->update([
                'read_at' => null,
            ]);

            BranchInboxUpdated::dispatch(
                branchId: $branch->id,
                messageId: $message->id,
                action: 'unread',
            );
        }

        return back()->with('success', 'Správa bola označená ako neprečítaná.');
    }

    public function destroy(Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_unless($message->branch_id === $branch->id, 404);

        $messageId = $message->id;

        $message->delete();

        BranchInboxUpdated::dispatch(
            branchId: $branch->id,
            messageId: $messageId,
            action: 'deleted',
        );

        return redirect()
            ->route('branches.inbox.index', [
                'branch' => $branch,
                'per_page' => request('per_page', 15),
                'type' => request('type'),
                'status' => request('status'),
            ])
            ->with('success', 'Správa bola odstránená.');
    }

    public function reply(Request $request, Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_unless($message->branch_id === $branch->id, 404);
        abort_unless($message->type === 'contact_form', 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        if (! $message->sender_email) {
            return back()->withErrors([
                'sender_email' => 'Správa nemá e-mailovú adresu, na ktorú je možné odpovedať.',
            ]);
        }

        Notification::route('mail', $message->sender_email)
            ->notify(new ContactReplyNotification(
                subject: $validated['subject'],
                bodyText: $validated['body'],
                branchName: $branch->name,
                originalMessage: $message->body,
            ));

        $message->replies()->create([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'recipient_email' => $message->sender_email,
            'sent_at' => now(),
        ]);

        $message->update([
            'read_at' => now(),
        ]);

        BranchInboxUpdated::dispatch(
            branchId: $branch->id,
            messageId: $message->id,
            action: 'replied',
        );

        return back()->with('success', 'Odpoveď bola odoslaná.');
    }
}