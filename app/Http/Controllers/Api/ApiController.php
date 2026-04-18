<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    protected function json(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function resource(JsonResource|ResourceCollection|array $resource, int $status = Response::HTTP_OK): JsonResponse
    {
        if ($resource instanceof JsonResource || $resource instanceof ResourceCollection) {
            $resource = $resource->resolve();
        }

        return $this->json($resource, $status);
    }

    protected function notFound(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_NOT_FOUND);
    }
}
