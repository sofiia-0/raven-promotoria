<?php

namespace App\Http\Controllers\Promoter;

use App\Models\VisitEvidence;
use App\Services\VisitEvidenceStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VisitEvidenceController
{
    public function upload(Request $request, VisitEvidenceStorage $storage)
    {
        try {
            $request->validate(['file' => ['required', 'file', 'max:10240'], 'batch_key' => ['required', 'uuid']], ['file.required' => 'Seleccione un archivo.', 'file.file' => 'El archivo no se cargó correctamente.', 'file.max' => 'Cada archivo no debe superar los 10 MiB.', 'batch_key.*' => 'La selección de archivos no es válida.']);
            $file = $storage->upload($request->user(), $request->string('batch_key')->toString(), $request->file('file'));

            return response()->json(['id' => $file->id, 'name' => $file->original_name, 'size' => $file->size, 'mime' => $file->mime, 'previewable' => $file->previewable()], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            return response()->json(['message' => 'No se pudo cargar el archivo. Inténtelo de nuevo.'], 503);
        }
    }

    public function download(Request $request, int $visit, string $evidence)
    {
        $file = $this->file($request, $visit, $evidence);
        try {
            return Storage::disk($file->disk)->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
        } catch (\Throwable) {
            return response()->json(['message' => 'El archivo no está disponible.'], 404);
        }
    }

    public function preview(Request $request, int $visit, string $evidence)
    {
        $file = $this->file($request, $visit, $evidence);
        abort_unless($file->previewable(), 404);
        try {
            return Storage::disk($file->disk)->response($file->path, null, ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"], 'inline');
        } catch (\Throwable) {
            return response()->json(['message' => 'La imagen no está disponible.'], 404);
        }
    }

    private function file(Request $request, int $visit, string $id): VisitEvidence
    {
        $request->user()->visits()->whereKey($visit)->firstOrFail();
        $file = VisitEvidence::whereKey($id)->where('visit_id', $visit)->where('status', 'attached')->firstOrFail();
        abort_unless(Gate::allows('view',$file), 404);

        return $file;
    }
}
