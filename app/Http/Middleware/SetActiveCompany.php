<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check() && ! session()->has('active_company_id')) {
            $first = \App\Models\Company::first();
            if ($first) {
                session(['active_company_id' => $first->id]);
            }
        }
        return $next($request);
    }
}
