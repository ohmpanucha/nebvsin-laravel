<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireLegacyAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->attributes->get('legacy_auth');
        $user = is_array($auth) ? ($auth['user'] ?? null) : null;

        if (! $user) {
            $nextPath = $request->getRequestUri();

            return redirect()->route('storefront.login', ['next' => $nextPath, 'lang' => $request->query('lang')]);
        }

        if (($user['role'] ?? 'user') !== 'admin') {
            return redirect()->route('storefront.home', ['lang' => $request->query('lang', 'en')]);
        }

        return $next($request);
    }
}
