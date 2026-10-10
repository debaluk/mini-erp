<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — AKUNTARA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --ak-blue: #0878e8; --ak-deep: #073b91; --ak-cyan: #10bcd4; }
        body { min-height: 100vh; overflow-x: hidden; position: relative; isolation: isolate; background: radial-gradient(ellipse at 15% 15%, rgba(16,188,212,.24), transparent 35%), radial-gradient(ellipse at 88% 82%, rgba(8,120,232,.32), transparent 38%), linear-gradient(135deg, #061b43 0%, #073b91 52%, #0878b8 100%); }
        body::before, body::after { content: ''; position: fixed; z-index: -1; border: 1px solid rgba(255,255,255,.12); border-radius: 50%; pointer-events: none; }
        body::before { width: 360px; height: 360px; top: -190px; right: -100px; box-shadow: 0 0 0 34px rgba(255,255,255,.025), 0 0 0 72px rgba(255,255,255,.02); }
        body::after { width: 300px; height: 300px; bottom: -190px; left: -100px; box-shadow: 0 0 0 32px rgba(255,255,255,.025), 0 0 0 68px rgba(255,255,255,.02); }
        .login-wrap { max-width: 365px; width: 100%; }
        .login-card { overflow: hidden; border: 1px solid rgba(255,255,255,.55); border-radius: 1.05rem; box-shadow: 0 24px 65px rgba(0,12,38,.28); }
        .login-brand { padding: 1.25rem 1.35rem 1.1rem; color: #fff; background: linear-gradient(110deg, var(--ak-deep), var(--ak-blue) 58%, var(--ak-cyan)); }
        .brand-icon { display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,.32); border-radius: 12px; background: rgba(255,255,255,.14); font-size: 1.2rem; }
        .brand-name { margin: 0; font-weight: 900; letter-spacing: .12em; }
        .brand-caption { margin-top: .2rem; color: rgba(255,255,255,.82); font-size: .74rem; }
        .login-body { padding: 1.35rem 1.4rem 1.4rem; background: #fff; }
        .login-body .form-label { color: #34445b; font-size: .82rem; font-weight: 650; margin-bottom: .35rem; }
        .login-body .form-control { min-height: 40px; border-color: #dce5f0; border-radius: .6rem; font-size: .9rem; }
        .login-body .form-control:focus { border-color: #65aaf6; box-shadow: 0 0 0 .2rem rgba(8,120,232,.13); }
        .login-submit { min-height: 42px; border: 0; border-radius: .6rem; background: linear-gradient(110deg, var(--ak-deep), var(--ak-blue) 60%, #0aaec8); font-size: .93rem; font-weight: 700; box-shadow: 0 7px 16px rgba(8,120,232,.2); }
        .login-submit:hover, .login-submit:focus { background: linear-gradient(110deg, #062f75, #066bd0 60%, #0796ad); }
        .login-footer { color: rgba(255,255,255,.72); font-size: .74rem; text-align: center; padding-top: 1rem; }
        @media (max-width: 480px) { .login-body { padding: 1.2rem; } .login-brand { padding: 1.15rem 1.2rem; } }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">

    <div class="login-wrap">
        <div class="login-card">
            <div class="login-brand">
                <div class="d-flex align-items-center gap-3">
                    <span class="brand-icon"><i class="bi bi-bar-chart-line-fill"></i></span>
                    <div>
                        <h1 class="brand-name h4">AKUNTARA</h1>
                    </div>
                </div>
            </div>
            <div class="login-body">

                <h2 class="h5 fw-bold mb-1">Silakan masuk untuk melanjutkan</h2>
                <p class="text-secondary small mb-4">Masukkan username dan password Anda.</p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.process') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input
                            type="text"
                            class="form-control form-control-lg"
                            id="username"
                            name="username"
                            value="{{ old('username') }}"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input
                            type="password"
                            class="form-control form-control-lg"
                            id="password"
                            name="password"
                            required
                        >
                    </div>

                    <div class="form-check mb-4">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            value="1"
                            id="remember"
                            name="remember"
                        >
                        <label class="form-check-label" for="remember">
                            Ingat saya
                        </label>
                    </div>

                    <button class="btn btn-primary btn-lg w-100 login-submit" type="submit">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                    </button>
                </form>

            </div>
        </div>
        <div class="login-footer">© {{ date('Y') }} AKUNTARA</div>
    </div>

</body>
</html>
