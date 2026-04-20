<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\StripeClient;

class StripeController extends Controller
{
  
    public function onboard(Request $request)
    {
        $user = $request->user();
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        // 1. Ensure Stripe ID exists
        if (empty($user->stripe_account_id)) {
            $account = $stripe->accounts->create(['type' => 'express', 'email' => $user->email]);
            $user->update(['stripe_account_id' => $account->id]);
        }

        $account = $stripe->accounts->retrieve($user->stripe_account_id);

        // 2. If details are NOT submitted, return an Onboarding link
        if (!$account->details_submitted) {
            $link = $stripe->accountLinks->create([
                'account' => $user->stripe_account_id,
                'refresh_url' => route('dashboard'),
                'return_url' => route('dashboard'),
                'type' => 'account_onboarding',
            ]);
            return response()->json(['url' => $link->url]);
        }

        // 3. If details ARE submitted, return a LOGIN link (Dashboard)
        // This allows the user to view their payouts/balance
        $loginLink = $stripe->accounts->createLoginLink($user->stripe_account_id);
        return response()->json(['url' => $loginLink->url]);
    }

    public function getLoginLink(Request $request)
    {
        $user = $request->user();
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        // 1. Ensure account exists
        if (empty($user->stripe_account_id)) {
            $account = $stripe->accounts->create(['type' => 'express', 'email' => $user->email]);
            $user->update(['stripe_account_id' => $account->id]);
        }

        $account = $stripe->accounts->retrieve($user->stripe_account_id);

        // 2. If 'details_submitted' is false, they MUST finish setup (Onboarding)
        if (!$account->details_submitted) {
            $link = $stripe->accountLinks->create([
                'account' => $user->stripe_account_id,
                'refresh_url' => route('dashboard'),
                'return_url' => route('dashboard'),
                'type' => 'account_onboarding',
            ]);
            return response()->json(['url' => $link->url]);
        }

        // 3. Only if 'details_submitted' is true can we generate a Login Link
        $link = $stripe->accounts->createLoginLink($user->stripe_account_id);
        return response()->json(['url' => $link->url]);
        }

    public function tipAuthor(Request $request, $authorId)
    {
        $author = \App\Models\User::findOrFail($authorId);

        // CRITICAL GUARD: Stop immediately if no ID is present
        if (empty($author->stripe_account_id)) {
            return response()->json(['message' => 'Author account not initialized.'], 400);
        }

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => 500,
            'currency' => 'usd',
            'transfer_data' => ['destination' => $author->stripe_account_id],
        ]);

        return response()->json(['clientSecret' => $paymentIntent->client_secret]);
    }
}