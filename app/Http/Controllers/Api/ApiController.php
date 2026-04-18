<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    protected function notImplemented(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
