<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e31b23">
    <meta name="description" content="Sign in to the Shimadzu DET production workspace.">
    <title>Sign in · Shimadzu Production</title>
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
                <div class="auth-story-label"><span></span>SHIMADZU · DET PRODUCTION</div>
                <div class="auth-story-copy"><span class="auth-overline">DET PRODUCTION DASHBOARD</span><h1>Your production.<br><em>One clear view.</em></h1></div>
                <div class="auth-purpose"><span aria-hidden="true"></span><p>Monitor station activity, track cycle times, and review daily production performance in one place.</p></div>
                <div class="auth-story-footer"><span>SHIMADZU · PRODUCTION INTELLIGENCE</span><span>DET</span></div>
            </section>

            <section class="auth-form-panel" aria-label="Sign in">
                <img class="auth-logo" src="{{ asset('images/shimadzu-logo.png') }}" alt="SHIMADZU" width="500" height="144">
                <div class="auth-form-heading"><span class="auth-overline">YOUR PRODUCTION WORKSPACE</span><h2>Welcome back<span>.</span></h2><p>Sign in to see your production day clearly.</p></div>
                @if ($errors->any())
                    <div class="auth-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form class="auth-form" method="POST" action="{{ route('login.store') }}" id="login-form">
                    @csrf
                    <div class="auth-field"><label for="username">Username</label><div class="auth-input-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg><input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="Enter your username" autocomplete="username" maxlength="80" autocapitalize="none" spellcheck="false" required @if($errors->has('username')) aria-invalid="true" @endif></div></div>
                    <div class="auth-field"><label for="password">Password</label><div class="auth-input-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="auth-password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
                    <label class="auth-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Keep me signed in</span></label>
                    <button type="submit" class="auth-submit" id="login-submit"><span>Sign in to workspace</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></button>
                </form>
                <p class="auth-access-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>Authorized personnel only.</p>
            </section>
        </div>
        <footer class="auth-footer"><span>SHIMADZU <i>·</i> DET Production Intelligence</span><span>Built for a better production day.</span></footer>
    </main>
</body>
</html>
