<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return response()->view('auth.login')->header('Cache-Control', 'no-store, private');
    }

    public function sessionToken(Request $request)
    {
        return response()->json(['token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt(array_merge($credentials, ['is_active' => true]))) {
            $request->session()->regenerate();
            if ($request->user()->must_change_password) {
                return redirect()->route('password.first.edit');
            }
            return redirect()->intended('dashboard');
        }

        // Check if user exists but is inactive
        $user = \App\Models\User::where('email', $credentials['email'])->first();
        if ($user && !$user->is_active) {
            return back()->withErrors([
                'email' => 'Your account is pending administrator approval.',
            ]);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
