<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($credentials['username']);

        // Kolom identitas pada tabel users adalah email, bukan username.
        // Tetap dukung input "owner" dengan mencocokkan bagian sebelum @.
        $user = User::query()->where('email', $identifier)->first();

        if (!$user && !str_contains($identifier, '@')) {
            $user = User::query()
                ->whereRaw("SUBSTRING_INDEX(email, '@', 1) = ?", [$identifier])
                ->first();
        }

        if ($user && $user->is_active && Auth::attempt([
            'email' => $user->email,
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors([
                'username' => 'Username atau password tidak sesuai, atau akun tidak aktif.',
            ])
            ->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
