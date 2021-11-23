<?php

namespace MatinUtils\LogSystem;

class Middleware
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        $response->header('PID', app('log-system')->getPid());
        app('log-system')->send();
        return $response;
    }
}
