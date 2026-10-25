<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * @param  array<array-key, mixed>|JsonResource|null  $data
     * @param  array<string, string|array<int, string>>  $headers
     */
    public static function success(array|JsonResource|null $data = null, ?string $message = null, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
        ], $status, $headers);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource::collection($paginator->getCollection())->resolve(request()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     * @param  array<string, string|array<int, string>>  $headers
     */
    public static function error(string $message, int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status, $headers);
    }
}
