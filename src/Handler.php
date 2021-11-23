<?php

namespace MatinUtils\LogSystem;

use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
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
    public function report(Throwable $exception)
    {
        if (config('lug.errorReporting')) {

            $excludes = config('lug.excludes');
            $exceptionClass = get_class($exception);
            if (!in_array($exceptionClass, $excludes)) {

                lugDebug($exception->getMessage(), [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'code' => $exception->getCode(),
                    'trace' => $exception->getTrace(),
                    'trace_string' => $exception->getTraceAsString(),
                    'exception' => get_class($exception),
                ]);
                http_response_code(500);
                die("pid: " . app('log-system')->getPID());
            }
        }
        parent::report($exception);
    }
}
