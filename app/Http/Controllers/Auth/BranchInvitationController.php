<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BranchInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\UserInvitationService;

class BranchInvitationController extends Controller
{
    public function __construct(private UserInvitationService $invitations)
    {
    }

    public function show(string $token): Response
    {
        $invitation = $this->findInvitationByToken($token);

        abort_if(! $invitation, 404);
        abort_if($invitation->isAccepted() || $invitation->isExpired(), 404);

        $existingUser = User::query()
            ->select(['id', 'first_name', 'last_name', 'email'])
            ->where('email', $invitation->email)
            ->first();

        if (auth()->check() && strcasecmp((string) auth()->user()->email, $invitation->email) === 0) {
            $this->invitations->acceptExistingBranchInvitation($invitation, auth()->user());

            return redirect()
                ->route('dashboard')
                ->with('success', 'Pozvánka bola prijatá.');
        }

        return Inertia::render('Auth/BranchInvitation', [
            'invitation' => [
                'entity_name' => $invitation->branch->name,
                'email' => $invitation->email,
                'token' => $token,
                'mode' => $existingUser ? 'existing_user' : 'new_user',
            ],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitationByToken($token);

        abort_if(! $invitation, 404);
        abort_if($invitation->isAccepted() || $invitation->isExpired(), 404);

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            if (auth()->check() && auth()->id() === $existingUser->id) {
                $this->invitations->acceptExistingBranchInvitation($invitation, $existingUser);

                return redirect()
                    ->route('dashboard')
                    ->with('success', 'Pozvánka bola prijatá.');
            }

            $data = $request->validate([
                'password' => ['required', 'string'],
            ]);

            if (! Auth::attempt(['email' => $existingUser->email, 'password' => $data['password']])) {
                return back()->withErrors([
                    'password' => 'Nesprávne heslo.',
                ])->withInput();
            }

            $request->session()->regenerate();

            $this->invitations->acceptExistingBranchInvitation($invitation, $existingUser);

            return redirect()
                ->route('dashboard')
                ->with('success', 'Pozvánka bola prijatá.');
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (strtolower($data['email']) !== strtolower($invitation->email)) {
            return back()->withErrors([
                'email' => 'Použite email z pozvánky.',
            ])->withInput();
        }

        $user = $this->invitations->createNewBranchUser($invitation, $data);

        event(new Registered($user));
        Auth::login($user);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Účet bol vytvorený a prístup k pobočke bol aktivovaný.');
    }

    private function findInvitationByToken(string $token): ?BranchInvitation
    {
        return BranchInvitation::query()
            ->with('branch:id,name')
            ->whereNull('accepted_at')
            ->get()
            ->first(fn (BranchInvitation $invitation) => Hash::check($token, $invitation->token_hash));
    }

}
