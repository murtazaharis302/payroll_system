<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeliAttendanceIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliAttendanceUploadController extends Controller
{
    public function __construct(
        protected DeliAttendanceIngestionService $ingestionService
    ) {}

    /**
     * Handle public machine-to-machine DELI automatic attendance upload endpoint.
     */
    public function handle(Request $request, string $publicId, string $token): JsonResponse
    {
        $rawContent = trim($request->getContent());

        if ($rawContent === '') {
            return response()->json(['error' => 'Empty request body.'], 422);
        }

        $decodedObject = json_decode($rawContent);

        if (json_last_error() !== JSON_ERROR_NONE || !($decodedObject instanceof \stdClass)) {
            return response()->json(['error' => 'Payload must be a valid JSON object.'], 422);
        }

        $payload = json_decode($rawContent, true) ?? [];

        $result = $this->ingestionService->handleRequest($publicId, $token, $payload);

        return response()->json($result['body'], $result['status']);
    }
}
