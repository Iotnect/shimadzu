@php
    $mode = $mode ?? 'login';
    [$heading, $description, $action, $submitLabel, $pendingLabel] = match ($mode) {
        'forgot' => ['Forgot password', 'Enter your account email to request a reset link.', 'password.email', 'Send reset link', 'Sending…'],
        'reset' => ['Reset password', 'Choose a new password with at least 12 characters.', 'password.update', 'Reset password', 'Resetting…'],
        default => ['Welcome back', 'Sign in to see your production day clearly.', 'login.store', 'Sign in to workspace', 'Signing in…'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e31b23">
    <meta name="description" content="Sign in to the Shimadzu production monitoring workspace.">
    <title>{{ $mode === 'login' ? 'Sign in' : $heading }} · Shimadzu Production</title>
    @if ($mode === 'reset')
        <meta name="referrer" content="no-referrer">
    @endif
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=shimadzu">
    <link rel="icon" type="image/x-icon" sizes="16x16 32x32 48x48" href="{{ asset('favicon.ico') }}?v=shimadzu">
    @vite(['resources/css/app.css', 'resources/js/login.js'])
</head>
<body class="auth-page">
    <main class="auth-layout">
        <div class="auth-topline"><span>PRODUCTION INTELLIGENCE</span></div>
        <div class="auth-card">
            <section class="auth-story" aria-label="Production workspace introduction">
                <div class="auth-story-grid" aria-hidden="true"></div>
                <div class="auth-story-label"><span></span>SHIMADZU · PRODUCTION MONITORING</div>
                <div class="auth-story-copy"><span class="auth-overline">PRODUCTION DASHBOARD</span><h1>Your production.<br><em>One clear view.</em></h1></div>
                <div class="auth-purpose"><span aria-hidden="true"></span><p>Monitor station activity, track cycle times, and review daily production performance in one place.</p></div>
                <div class="auth-story-footer"><span>SHIMADZU · PRODUCTION INTELLIGENCE</span></div>
            </section>

            <section class="auth-form-panel" aria-labelledby="auth-heading">
                <img class="auth-logo" src="{{ asset('images/shimadzu-logo.png') }}" alt="SHIMADZU" width="500" height="144">
                <div class="auth-form-heading"><span class="auth-overline">YOUR PRODUCTION WORKSPACE</span><h2 id="auth-heading">{{ $heading }}<span>.</span></h2><p>{{ $description }}</p></div>
                @if ($errors->any())
                    <div class="auth-error" role="alert">{{ $errors->first() }}</div>
                @endif
                @if (session('status'))
                    <div class="auth-status" role="status">{{ session('status') }}</div>
                @endif
                <form class="auth-form" method="POST" action="{{ route($action) }}" id="login-form">
                    @csrf
                    @if ($mode === 'reset')
                        <input type="hidden" name="token" value="{{ $token }}">
                    @endif
                    <div class="auth-field">
                        <label for="email">Email</label>
                        <div class="auth-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <input id="email" name="email" type="email" value="{{ old('email', $email ?? '') }}" placeholder="Enter your email" autocomplete="username" maxlength="254" autocapitalize="none" spellcheck="false" required @if($errors->has('email')) aria-invalid="true" @endif>
                        </div>
                    </div>
                    @if ($mode !== 'forgot')
                        <div class="auth-field">
                            <label for="password">{{ $mode === 'reset' ? 'New password' : 'Password' }}</label>
                            <div class="auth-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
                                <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="{{ $mode === 'reset' ? 'new-password' : 'current-password' }}" required @if($mode === 'reset') minlength="12" maxlength="72" @endif>
                                <button type="button" class="auth-password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                            </div>
                        </div>
                    @endif
                    @if ($mode === 'reset')
                        <div class="auth-field">
                            <label for="password_confirmation">Confirm new password</label>
                            <div class="auth-input-wrap"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div>
                        </div>
                    @endif
                    @if ($mode === 'login' && config('auth.providers.users.driver') === 'eloquent')
                        <div class="auth-form-options">
                            <label class="auth-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Keep me signed in</span></label>
                            <a class="auth-form-link" href="{{ route('password.request') }}">Forgot password?</a>
                        </div>
                    @endif
                    <button type="submit" class="auth-submit" id="login-submit" data-pending-label="{{ $pendingLabel }}"><span>{{ $submitLabel }}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></button>
                    @if ($mode !== 'login')
                        <a class="auth-form-link" href="{{ $mode === 'reset' ? route('password.request') : route('login') }}">{{ $mode === 'reset' ? 'Request a new reset link' : 'Back to sign in' }}</a>
                    @endif
                </form>
                <p class="auth-access-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>Authorized personnel only.</p>
            </section>
        </div>
        <footer class="auth-footer"><span>SHIMADZU <i>·</i> Production Intelligence</span><span>Built for a better production day.</span></footer>
    </main>
</body>
</html>
