<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use SensitiveParameter;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        $this->ensureAvailable();

        return view('auth.login', ['mode' => 'forgot']);
    }

    public function send(Request $request): RedirectResponse
    {
        $this->ensureAvailable();

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        Password::sendResetLink(['email' => mb_strtolower($validated['email'])]);

        return redirect()->route('password.request')->with('status', 'If an account matches that email, a password reset link will be sent.');
    }

    public function edit(Request $request, string $token): View
    {
        $this->ensureAvailable();

        return view('auth.login', ['mode' => 'reset', 'token' => $token, 'email' => $request->string('email')->toString()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAvailable();

        $credentials = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        $status = DB::transaction(fn (): string => Password::reset($credentials, function (User $user, #[SensitiveParameter] string $password): void {
            $user->password = $password;
            $user->setRememberToken(null);
            $user->save();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table'))
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            event(new PasswordReset($user));
        }));

        if ($status !== Password::PasswordReset) {
            return back()->withErrors(['email' => 'This reset link is invalid or expired. Request a new link and try again.']);
        }

        return redirect()->route('login')->with('status', 'Your password was reset. Sign in with your new password.');
    }

    private function ensureAvailable(): void
    {
        abort_unless(config('auth.providers.users.driver') === 'eloquent', 404);
    }
}
