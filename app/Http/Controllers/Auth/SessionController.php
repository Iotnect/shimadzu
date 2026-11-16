<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $environmentAccount = config('auth.providers.users.driver') === 'environment';
        $username = $environmentAccount ? config('auth.providers.users.username', '') : '';
        $emailRules = ['required', 'string', 'max:254'];
        if ($username === '') {
            $emailRules[] = 'email';
        }

        $credentials = $request->validate([
            'email' => $emailRules,
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $identifier = mb_strtolower($credentials['email']);
        $email = $username !== '' && hash_equals($username, $identifier)
            ? config('auth.providers.users.email')
            : $identifier;

        if (! Auth::attempt([
            'email' => $email,
            'password' => $credentials['password'],
        ], $request->boolean('remember') && ! $environmentAccount)) {
            throw ValidationException::withMessages([
                'email' => $username !== '' ? 'The username, email, or password is incorrect.' : 'The email or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
