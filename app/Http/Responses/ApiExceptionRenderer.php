<?php

namespace App\Http\Responses;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionRenderer
{
    /**
     * Render any exception on an API request as the standard JSON error envelope.
     * Returns null for non-API requests so Laravel's default (HTML / Inertia) rendering is used.
     */
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $exception instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => ApiResponse::error('The given data was invalid.', 422, $exception->errors()),
            $exception instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
            $exception instanceof AccessDeniedHttpException => ApiResponse::error($this->forbiddenMessage($exception), 403),
            $exception instanceof NotFoundHttpException => ApiResponse::error('Resource not found.', 404),
            $exception instanceof MethodNotAllowedHttpException => ApiResponse::error('Method not allowed.', 405, headers: $exception->getHeaders()),
            $exception instanceof TooManyRequestsHttpException => ApiResponse::error('Too many requests.', 429, headers: $exception->getHeaders()),
            $exception instanceof HttpExceptionInterface => ApiResponse::error(
                $exception->getMessage() !== '' ? $exception->getMessage() : (Response::$statusTexts[$exception->getStatusCode()] ?? 'Error.'),
                $exception->getStatusCode(),
                headers: $exception->getHeaders(),
            ),
            default => ApiResponse::error(config('app.debug') ? $exception->getMessage() : 'Server error.', 500),
        };
    }

    private function forbiddenMessage(AccessDeniedHttpException $exception): string
    {
        $message = $exception->getMessage();

        return $message === '' || $message === 'This action is unauthorized.' ? 'Forbidden.' : $message;
    }
}
