<?php

namespace App\Exceptions;

use Exception;

class ExternalServiceException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => 'External Service Unavailable',
            'message' => config('app.debug') ? $this->getMessage() : 'Try again later'
        ], 503);
    }
}