<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const FORM_ISSUED_AT_SESSION_KEY = 'auth.login_form_issued_at';

    public const CAPTCHA_SESSION_KEY = 'auth.login_captcha';

    private const MINIMUM_SECONDS_BEFORE_SUBMIT = 1;

    private const MAXIMUM_SECONDS_BEFORE_SUBMIT = 7200;

    private const CAPTCHA_MAXIMUM_SECONDS_BEFORE_SUBMIT = 900;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'captcha_answer' => ['required', 'string', 'max:10'],
            'login_website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $this->ensurePassesBotVerification();

        $login = $this->string('email')->toString();
        $loginColumn = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([
            $loginColumn => $login,
            'password' => $this->string('password')->toString(),
            'status' => 'active',
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Email, kata sandi, atau status akun tidak valid.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->session()->forget(self::FORM_ISSUED_AT_SESSION_KEY);
        $this->session()->forget(self::CAPTCHA_SESSION_KEY);
    }

    /**
     * Reject automated login posts that did not load the login page first.
     *
     * @throws ValidationException
     */
    private function ensurePassesBotVerification(): void
    {
        $issuedAt = $this->session()->get(self::FORM_ISSUED_AT_SESSION_KEY);
        $elapsedSeconds = is_numeric($issuedAt) ? now()->timestamp - (int) $issuedAt : null;

        if (
            filled($this->input('login_website'))
            || $elapsedSeconds === null
            || $elapsedSeconds < self::MINIMUM_SECONDS_BEFORE_SUBMIT
            || $elapsedSeconds > self::MAXIMUM_SECONDS_BEFORE_SUBMIT
        ) {
            RateLimiter::hit($this->throttleKey(), 300);

            throw ValidationException::withMessages([
                'email' => 'Verifikasi keamanan gagal. Muat ulang halaman login, lalu coba lagi.',
            ]);
        }

        $captcha = $this->session()->get(self::CAPTCHA_SESSION_KEY);
        $captchaIssuedAt = is_array($captcha) ? ($captcha['issued_at'] ?? null) : null;
        $captchaAnswer = is_array($captcha) ? ($captcha['answer'] ?? null) : null;
        $captchaElapsedSeconds = is_numeric($captchaIssuedAt) ? now()->timestamp - (int) $captchaIssuedAt : null;
        $submittedAnswer = trim($this->string('captcha_answer')->toString());

        if (
            ! is_string($captchaAnswer)
            || $captchaElapsedSeconds === null
            || $captchaElapsedSeconds < 0
            || $captchaElapsedSeconds > self::CAPTCHA_MAXIMUM_SECONDS_BEFORE_SUBMIT
            || ! hash_equals($captchaAnswer, $submittedAnswer)
        ) {
            RateLimiter::hit($this->throttleKey(), 300);

            throw ValidationException::withMessages([
                'captcha_answer' => 'Jawaban verifikasi keamanan tidak sesuai. Silakan coba lagi.',
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan login. Silakan tunggu {$seconds} detik, lalu coba kembali.",
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
