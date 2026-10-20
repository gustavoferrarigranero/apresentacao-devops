<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class AttachReleaseContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $release = (string) config('app.release');
        Context::add('release', $release);
        $response = $next($request);
        $response->headers->set('X-App-Release', $release);

        return $response;
    }
}
