<?php

declare(strict_types=1);

namespace TurboStreamExport\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TurboStreamExport\Jobs\ProcessExportJob;
use TurboStreamExport\Services\ExportService;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService
    ) {}

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'columns' => ['required', 'array', 'min:1'],
            'filters' => ['sometimes', 'array'],
            'format' => ['sometimes', 'string', 'in:csv,xlsx,pdf,docx,sql'],
            'filename' => ['sometimes', 'string'],
        ]);

        $exportId = (string) Str::uuid();
        
        $modelClass = $validated['model'];
        $query = $modelClass::query();

        if (!Gate::allows('export', $query->getModel())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!empty($validated['filters'])) {
            $query->where($validated['filters']);
        }

        $filename = $validated['filename'] ?? 'export_' . now()->format('Y-m-d_His');
        $format = $validated['format'] ?? 'csv';

        ProcessExportJob::dispatch(
            $exportId,
            $modelClass,
            $validated['columns'],
            $validated['filters'] ?? [],
            $filename,
            $format,
            $request->user()?->id ?? 0
        );

        return response()->json([
            'export_id' => $exportId,
            'status' => 'queued',
            'message' => 'Export job has been queued',
        ], 202);
    }

    public function progress(string $exportId): JsonResponse
    {
        $progress = $this->exportService->getProgress($exportId);

        if ($progress['status'] === 'not_found') {
            return response()->json(['error' => 'Export not found'], 404);
        }

        return response()->json($progress);
    }

    public function download(string $exportId, Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $progress = $this->exportService->getProgress($exportId);

        if ($progress['status'] !== 'completed') {
            return response()->json(['error' => 'Export not ready'], 400);
        }

        if (!Gate::allows('download-export', $progress['file_path'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $filePath = $progress['file_path'];
        
        $relativePath = str_replace(storage_path('app') . '/', '', $filePath);
        
        if (!Storage::disk(config('turbo-export.disk'))->exists($relativePath)) {
            return response()->json(['error' => 'File not found', 'path' => $relativePath], 404);
        }

        return Storage::disk(config('turbo-export.disk'))->download(
            $relativePath,
            basename($filePath),
            ['Content-Type' => 'application/octet-stream']
        );
    }
}
