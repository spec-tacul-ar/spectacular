<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Exceptions\DriverMissingConfigurationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteController extends Controller
{
    public function redirect(string $provider)
    {
        try {
            return Socialite::driver($provider)->redirect();
        } catch (DriverMissingConfigurationException $exception) {
            abort(404);
        }
    }

    public function callback(Request $request, string $provider)
    {
        try {
            $social = Socialite::driver($provider)->user();
        } catch (DriverMissingConfigurationException $exception) {
            abort(404);
        } catch (InvalidStateException $exception) {
            return response()->view('auth.error', ['message' => 'Something went wrong while we were communicating with the OAuth provider.'], 400);
        }

        $account = Account::findBySocial($provider, $social->id);

        if (! $account) {
            $email = $social->getEmail();

            if ($email && Account::findByEmail($email)) {
                return response()->view('auth.error', [
                    'message' => "An account with this email address already exists, but it is not connected to this social login provider. Please sign in using your existing method.",
                ], 409);
            }

            if (! config('spectacular.registration')) {
                abort(404);
            }

            try {
                $validated = Validator::make([
                    'name' => $social->getName(),
                    'email' => $email,
                ], [
                    'name' => ['required', 'string', 'max:250'],
                    'email' => ['required', 'email:filter', 'max:250', Rule::unique(Account::class)],
                ])->validate();
            } catch (ValidationException $exception) {
                return response()->view('auth.error', ['message' => 'Could not create your account. ' . $exception->getMessage()], 422);
            }

            $account = new Account($validated);
            $account->socialite_provider = $provider;
            $account->socialite_provider_id = $social->id;
            $account->save();
        }

        if (! $account->hasVerifiedEmail()) {
            $account->markEmailAsVerified();
        }

        Auth::login($account);

        return redirect('/app');
    }
}
