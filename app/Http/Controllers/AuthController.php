<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            Audit::record('login_failed', null, 'ያልተሳካ መግቢያ: '.$credentials['email']);
            throw ValidationException::withMessages(['email' => 'ኢሜይሉ ወይም የይለፍ ቃሉ ትክክል አይደለም (ወይም መለያው ታግዷል)።']);
        }

        $request->session()->regenerate();
        Audit::record('login', $request->user(), $request->user()->email);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            Audit::record('logout', $request->user(), $request->user()->email);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
