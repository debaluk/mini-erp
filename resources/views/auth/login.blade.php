<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #f4f6f9; }
        .login-card { max-width: 430px; width: 100%; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="login-card">
        <div class="text-center mb-4">
            <h1 class="fw-bold mb-1">Mini ERP</h1>
            <div class="text-secondary">Retail · Produksi Batako · Armada · Akuntansi</div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h4 class="mb-1">Masuk</h4>
                <p class="text-secondary mb-4">Silakan masuk untuk melanjutkan.</p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Ingat saya</label>
                    </div>

                    <button class="btn btn-primary btn-lg w-100" type="submit">Masuk</button>
                </form>
            </div>
        </div>

        <div class="text-center text-secondary small mt-4">Mini ERP — Sistem Informasi Terpadu</div>
    </div>
</body>
</html>
