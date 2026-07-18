<?php

namespace App\Http\Middleware;

use App\Support\LegacyAuthService;
use App\Support\StorefrontCartService;
use App\Support\StorefrontLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class HydrateLegacyAuth
{
    protected $auth;
    protected $cart;

    public function __construct(LegacyAuthService $auth, StorefrontCartService $cart)
    {
        $this->auth = $auth;
        $this->cart = $cart;
    }

    public function handle(Request $request, Closure $next)
    {
        $locale = StorefrontLocale::resolve($request);
        $authState = $this->auth->safeCurrentUserFromToken($request->session()->get('legacy_auth_token'));

        if ($authState) {
            $request->attributes->set('legacy_auth', $authState);
            $request->session()->put('legacy_auth_user', $authState['user']);
        } else {
            $request->session()->forget(['legacy_auth_token', 'legacy_auth_user']);
        }

        View::share('storefrontLocale', $locale);
        View::share('storefrontAuthUser', $authState['user'] ?? null);
        View::share('storefrontLayoutCopy', StorefrontLocale::copy('layout', $locale));
        View::share('storefrontCartItems', $this->cart->items($request));
        View::share('storefrontCartCount', $this->cart->totalItems($request));
        View::share('storefrontCartSubtotal', $this->cart->subtotal($request));

        return $next($request);
    }
}
