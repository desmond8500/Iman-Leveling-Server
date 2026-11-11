<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProgressRequest;
use App\Models\Progress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $progress = $request->user()->progress;

        if (! $progress) {
            return response()->json(['message' => 'Aucune progression enregistrée.'], 404);
        }

        return response()->json([
            'data' => $progress->payload(),
            'updated_at' => $progress->updated_at?->toIso8601String(),
        ]);
    }

    public function update(UpdateProgressRequest $request): JsonResponse
    {
        $progress = $request->user()->progress()->updateOrCreate(
            [],
            ['data' => Progress::sanitize($request->validated('data'))],
        );

        return response()->json([
            'updated_at' => $progress->updated_at->toIso8601String(),
        ]);
    }
}
