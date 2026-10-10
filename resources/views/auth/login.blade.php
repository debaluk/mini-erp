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
        body { min-height: 100vh; background: radial-gradient(circle at 12% 12%, rgba(16,188,212,.13), transparent 32%), radial-gradient(circle at 88% 88%, rgba(8,120,232,.13), transparent 35%), #f3f7fc; }
        .login-wrap { max-width: 440px; width: 100%; }
        .login-card { overflow: hidden; border: 1px solid rgba(13,87,170,.09); border-radius: 1.15rem; box-shadow: 0 22px 60px rgba(23,62,112,.12); }
        .login-brand { padding: 1.6rem 1.5rem 1.35rem; color: #fff; background: linear-gradient(110deg, var(--ak-deep), var(--ak-blue) 58%, var(--ak-cyan)); }
        .brand-icon { display: inline-flex; width: 46px; height: 46px; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,.32); border-radius: 13px; background: rgba(255,255,255,.14); font-size: 1.35rem; }
        .brand-name { margin: 0; font-weight: 900; letter-spacing: .12em; }
        .brand-caption { margin-top: .25rem; color: rgba(255,255,255,.82); font-size: .78rem; }
        .login-body { padding: 1.75rem; background: #fff; }
        .login-body .form-label { color: #34445b; font-size: .86rem; font-weight: 650; }
        .login-body .form-control { min-height: 46px; border-color: #dce5f0; border-radius: .65rem; font-size: .95rem; }
        .login-body .form-control:focus { border-color: #65aaf6; box-shadow: 0 0 0 .22rem rgba(8,120,232,.13); }
        .login-submit { min-height: 47px; border: 0; border-radius: .65rem; background: linear-gradient(110deg, var(--ak-deep), var(--ak-blue) 60%, #0aaec8); font-weight: 700; box-shadow: 0 7px 16px rgba(8,120,232,.2); }
        .login-submit:hover, .login-submit:focus { background: linear-gradient(110deg, #062f75, #066bd0 60%, #0796ad); }
        .login-footer { color: #8290a3; font-size: .75rem; text-align: center; padding-top: 1.2rem; }
        @media (max-width: 480px) { .login-body { padding: 1.35rem; } .login-brand { padding: 1.35rem; } }
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
                        <div class="brand-caption">Silakan masuk untuk melanjutkan</div>
                    </div>
                </div>
            </div>
            <div class="login-body">

                <h2 class="h5 fw-bold mb-1">Masuk ke akun</h2>
                <p class="text-secondary small mb-4">Gunakan email dan password Anda.</p>

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
