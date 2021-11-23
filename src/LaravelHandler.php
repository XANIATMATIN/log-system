<?php

namespace MatinUtils\LogSystem;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class LaravelHandler extends ExceptionHandler
{

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(\Throwable $e)
    {
        if (config('lug.errorReporting')) {
            $excludes = config('lug.excludes');
            $exceptionClass = get_class($e);
            if (!in_array($exceptionClass, $excludes)) {
                lugDebug($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTrace(),
                    'trace_string' => $e->getTraceAsString(),
                    'exception' => get_class($e),
                ]);
                http_response_code(500);
                die("pid: " . app('log-system')->getPID());
            }
        }
        parent::report($e);
    }
}
