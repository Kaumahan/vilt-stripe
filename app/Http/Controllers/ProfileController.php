<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{

    public function index()
    {
        $user = auth()->user();
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        
        $payoutsEnabled = false;
        if ($user->stripe_account_id) {
            $account = $stripe->accounts->retrieve($user->stripe_account_id);
            $payoutsEnabled = $account->payouts_enabled;
        }

        return Inertia::render('Dashboard', [
            'stripeConnected' => !empty($user->stripe_account_id),
            'payoutsEnabled' => $payoutsEnabled,
            'authorId' => $user->id,
                'books' => [
                ['id' => 1, 'title' => 'The Laravel Guide', 'author_id' => 1, 'author_name' => 'John Doe'],
                ['id' => 2, 'title' => 'Vue.js Mastery', 'author_id' => 3, 'author_name' => 'Jane Smith'],
            ]
        ]);
    }
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
