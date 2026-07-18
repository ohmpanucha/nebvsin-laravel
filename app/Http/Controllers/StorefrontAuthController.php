<?php

namespace App\Http\Controllers;

use App\Support\LegacyAuthService;
use App\Support\StorefrontLocale;
use App\Support\StorefrontPasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontAuthController extends Controller
{
    protected $auth;

    protected $passwordReset;

    public function __construct(LegacyAuthService $auth, StorefrontPasswordResetService $passwordReset)
    {
        $this->auth = $auth;
        $this->passwordReset = $passwordReset;
    }

    public function showLogin(Request $request): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);

        return view('storefront.auth.login', [
            'copy' => $copy,
            'pageTitle' => $copy['login_title'].' | NEBVSIN',
            'pageDescription' => $copy['login_caption'],
            'canonicalUrl' => route('storefront.login', ['lang' => $locale]),
            'robotsMeta' => 'noindex, nofollow',
            'nextPath' => $request->query('next', '/'),
            'statusMessage' => session('auth_status'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);
        $result = $this->auth->attemptLogin(
            (string) $request->input('email'),
            (string) $request->input('password'),
            (int) env('TOKEN_TTL_HOURS', 24)
        );

        if (! empty($result['error'])) {
            return back()
                ->withInput($request->only('email'))
                ->with('auth_status', $copy[$result['error']] ?? $copy['invalid_credentials']);
        }

        $request->session()->put('legacy_auth_token', $result['token']);
        $request->session()->put('legacy_auth_user', $result['user']);

        return redirect($request->input('next', '/'))
            ->with('auth_status', str_replace(':email', $result['user']['email'], $copy['signed_in_as']));
    }

    public function showRegister(Request $request): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);

        return view('storefront.auth.register', [
            'copy' => $copy,
            'pageTitle' => $copy['register_title'].' | NEBVSIN',
            'pageDescription' => $copy['register_caption'],
            'canonicalUrl' => route('storefront.register', ['lang' => $locale]),
            'robotsMeta' => 'noindex, nofollow',
            'nextPath' => $request->query('next', '/'),
            'statusMessage' => session('auth_status'),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);
        $password = (string) $request->input('password');
        $confirmPassword = (string) $request->input('confirm_password');

        if ($password !== $confirmPassword) {
            return back()
                ->withInput($request->only('display_name', 'email'))
                ->with('auth_status', $copy['password_mismatch']);
        }

        $result = $this->auth->register([
            'display_name' => $request->input('display_name'),
            'email' => $request->input('email'),
            'password' => $password,
        ], (int) env('TOKEN_TTL_HOURS', 24));

        if (! empty($result['error'])) {
            return back()
                ->withInput($request->only('display_name', 'email'))
                ->with('auth_status', $copy[$result['error']] ?? $copy['required']);
        }

        $request->session()->put('legacy_auth_token', $result['token']);
        $request->session()->put('legacy_auth_user', $result['user']);

        return redirect($request->input('next', '/'))
            ->with('auth_status', str_replace(':email', $result['user']['email'], $copy['registered_as']));
    }

    public function showForgotPassword(Request $request): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);

        return view('storefront.auth.forgot-password', [
            'copy' => $copy,
            'pageTitle' => $copy['forgot_password_title'].' | NEBVSIN',
            'pageDescription' => $copy['forgot_password_caption'],
            'canonicalUrl' => route('storefront.password.request', ['lang' => $locale]),
            'robotsMeta' => 'noindex, nofollow',
            'statusMessage' => session('auth_status'),
        ]);
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);
        $email = strtolower(trim((string) $request->input('email')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()
                ->withInput($request->only('email'))
                ->with('auth_status', $copy['invalid_email']);
        }

        $this->passwordReset->sendResetLink($email, $locale);

        return back()->with('auth_status', $copy['reset_link_sent']);
    }

    public function showResetPassword(Request $request, string $token): View
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);

        return view('storefront.auth.reset-password', [
            'copy' => $copy,
            'pageTitle' => $copy['reset_password_title'].' | NEBVSIN',
            'pageDescription' => $copy['reset_password_caption'],
            'canonicalUrl' => route('storefront.password.request', ['lang' => $locale]),
            'robotsMeta' => 'noindex, nofollow',
            'statusMessage' => session('auth_status'),
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $locale = StorefrontLocale::resolve($request);
        $copy = StorefrontLocale::copy('auth', $locale);
        $email = strtolower(trim((string) $request->input('email')));
        $token = (string) $request->input('token');
        $password = (string) $request->input('password');
        $confirmPassword = (string) $request->input('confirm_password');

        if ($password !== $confirmPassword) {
            return back()
                ->withInput($request->only('email', 'token'))
                ->with('auth_status', $copy['password_mismatch']);
        }

        if (strlen($password) < 8) {
            return back()
                ->withInput($request->only('email', 'token'))
                ->with('auth_status', $copy['password_minimum']);
        }

        if (! $this->passwordReset->reset($email, $token, $password)) {
            return back()
                ->withInput($request->only('email', 'token'))
                ->with('auth_status', $copy['reset_link_invalid']);
        }

        return redirect()
            ->route('storefront.login', ['lang' => $locale])
            ->with('auth_status', $copy['password_reset_complete']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout($request->session()->get('legacy_auth_token'));
        $request->session()->forget(['legacy_auth_token', 'legacy_auth_user']);

        return redirect()->route('storefront.home', ['lang' => StorefrontLocale::resolve($request)]);
    }
}
