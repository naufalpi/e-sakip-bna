<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        $request->session()->put(LoginRequest::FORM_ISSUED_AT_SESSION_KEY, now()->timestamp);

        return Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
            'captchaQuestion' => $this->issueCaptcha($request),
        ]);
    }

    /**
     * Issue a new CAPTCHA without reloading the login form.
     */
    public function captcha(Request $request): JsonResponse
    {
        return response()->json([
            'captchaQuestion' => $this->issueCaptcha($request),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        $redirect = redirect()->intended(route('dashboard', absolute: false));

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => $redirect->getTargetUrl(),
            ]);
        }

        return $redirect;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function issueCaptcha(Request $request): string
    {
        $firstNumber = random_int(3, 12);
        $secondNumber = random_int(2, 9);

        $request->session()->put(LoginRequest::CAPTCHA_SESSION_KEY, [
            'answer' => (string) ($firstNumber + $secondNumber),
            'issued_at' => now()->timestamp,
        ]);

        return "{$firstNumber} + {$secondNumber} = ?";
    }
}
