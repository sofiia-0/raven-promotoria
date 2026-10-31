<?php

namespace App\Http\Controllers\Coordinator;

use App\Models\VisitEvidence;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Consulta de evidencias para supervisión. Mismas cabeceras defensivas que el acceso de Promotora. */
class VisitEvidenceController
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
        'Content-Security-Policy' => "default-src 'none'; sandbox",
    ];

    public function download(int $visit, string $evidence)
    {
        $file = $this->file($visit, $evidence);
        try {
            return Storage::disk($file->disk)->download($file->path, $file->original_name, self::HEADERS);
        } catch (\Throwable) {
            return response()->json(['message' => 'El archivo no está disponible.'], 404);
        }
    }

    public function preview(int $visit, string $evidence)
    {
        $file = $this->file($visit, $evidence);
        abort_unless($file->previewable(), 404);
        try {
            return Storage::disk($file->disk)->response($file->path, null, ['Content-Type' => $file->mime] + self::HEADERS, 'inline');
        } catch (\Throwable) {
            return response()->json(['message' => 'La imagen no está disponible.'], 404);
        }
    }

    private function file(int $visit, string $id): VisitEvidence
    {
        $file = VisitEvidence::query()->whereKey($id)->where('visit_id', $visit)->where('status', 'attached')->firstOrFail();
        abort_unless(Gate::allows('view', $file), 404);

        return $file;
    }
}
