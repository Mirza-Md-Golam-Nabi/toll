<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncPushRequest;
use App\Services\Sync\SyncIngestService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(private SyncIngestService $ingest) {}

    public function push(SyncPushRequest $request): JsonResponse
    {
        $stored = $this->ingest->ingest(
            $request->attributes->get('booth'),
            $request->validated('table'),
            $request->records(),
        );

        return response()->json(['stored' => $stored]);
    }

    public function master(): JsonResponse
    {
        return response()->json(['data' => $this->ingest->masterData()]);
    }
}
