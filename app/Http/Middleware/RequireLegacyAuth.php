<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireLegacyAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->attributes->get('legacy_auth')) {
            $nextPath = $request->getRequestUri();

            return redirect()->route('storefront.login', ['next' => $nextPath, 'lang' => $request->query('lang')]);
        }

        return $next($request);
    }
}
