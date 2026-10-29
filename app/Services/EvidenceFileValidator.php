<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Comprueba el contenido sin ejecutar documentos ni extraer archivos a rutas del ZIP. */
class EvidenceFileValidator
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'docx', 'xlsx', 'pptx', 'zip', 'txt'];

    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid() || ! $file->getRealPath()) {
            $this->fail('El archivo no se cargó correctamente. Inténtelo de nuevo.');
        }
        $name = basename(str_replace(chr(92), '/', $file->getClientOriginalName()));
        $name = trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($name === '' || mb_strlen($name) > 240 || ! $this->allowedName($name) || ! in_array($extension, self::EXTENSIONS, true)) {
            $this->fail('Seleccione un formato de evidencia permitido.');
        }
        $size = filesize($file->getRealPath());
        if (! $size || $size > config('visit_evidence.max_file_bytes')) {
            $this->fail('Cada archivo debe tener contenido y no superar los 10 MiB.');
        }
        $mime = $this->inspectPath($file->getRealPath(), $extension, 0);

        return ['original_name' => $name, 'extension' => $extension, 'mime' => $mime, 'size' => $size, 'sha256' => hash_file('sha256', $file->getRealPath())];
    }

    private function allowedName(string $name): bool
    {
        return ! preg_match('/(?:^|\.)(?:php\d*|phtml|phar|html?|svg|js|mjs|exe|dll|com|bat|cmd|ps1|sh|py|rb|vbs|msi)(?:\.|$)/i', $name);
    }

    private function inspectPath(string $path, string $extension, int $depth): string
    {
        if (! class_exists(\finfo::class)) {
            $this->fail('No es posible comprobar archivos en este momento. Consulte con soporte.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $bytes = file_get_contents($path);
        if (! is_string($bytes)) {
            $this->fail('No se pudo comprobar el archivo.');
        }
        if (preg_match('/<\?(?:php|=)|<script\b|<!DOCTYPE\s+html/i', $bytes)) {
            $this->fail('El archivo contiene contenido activo no permitido.');
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $expected = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension];
            $image = @getimagesize($path);
            if ($mime !== $expected || ! $image || ($image['mime'] ?? null) !== $expected || $image[0] < 1 || $image[1] < 1 || $image[0] > 16000 || $image[1] > 16000 || $image[0] * $image[1] > 50000000) {
                $this->fail('La imagen no es válida o supera las dimensiones permitidas.');
            }

            return $expected;
        }
        if (in_array($extension, ['heic', 'heif'], true)) {
            if (! in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true)) {
                $this->fail('El contenido no corresponde a una imagen HEIC/HEIF.');
            }
            $this->checkHeif($bytes);

            return $mime;
        }
        if ($extension === 'pdf') {
            if ($mime !== 'application/pdf' || ! preg_match('/^%PDF-(?:1\.[0-7]|2\.0)/', $bytes) || ! preg_match('/%%EOF\s*$/', $bytes)) {
                $this->fail('El contenido no corresponde a un PDF válido.');
            }
            $decodedNames = preg_replace_callback('/#([0-9a-f]{2})/i', fn ($match) => chr(hexdec($match[1])), $bytes);
            if (preg_match('~/\s*(?:JavaScript|JS|Launch|RichMedia|EmbeddedFile|OpenAction|AA|Encrypt)\b~i', $decodedNames)) {
                $this->fail('No se permiten PDF con acciones, scripts ni archivos incrustados.');
            }

            return 'application/pdf';
        }
        if ($extension === 'txt') {
            if ($mime !== 'text/plain' || ! mb_check_encoding($bytes, 'UTF-8') || str_contains($bytes, "\0") || preg_match('/^#!|<html\b|<script\b|<\?/i', $bytes)) {
                $this->fail('El archivo debe ser texto UTF-8 sin contenido activo.');
            }

            return 'text/plain';
        }
        if (in_array($extension, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
            if ($extension === 'zip' && $depth > 0) {
                $this->fail('No se permiten archivos ZIP anidados.');
            }
            if (! str_starts_with($bytes, "PK\x03\x04") || ! in_array($mime, ['application/zip', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'], true)) {
                $this->fail('El documento no corresponde al formato indicado.');
            }
            $this->checkZip($path, $extension, $depth);

            return ['zip' => 'application/zip', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'][$extension];
        }
        $this->fail('Formato no permitido.');
    }

    private function checkHeif(string $bytes): void
    {
        $offset = 0;
        $types = [];
        $brands = '';
        $length = strlen($bytes);
        while ($offset < $length) {
            if ($length - $offset < 8) {
                $this->fail('El contenedor HEIC/HEIF está incompleto.');
            }
            $size = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);
            $header = 8;
            if ($size === 1) {
                if ($length - $offset < 16) {
                    $this->fail('Contenedor de imagen no válido.');
                }$large = unpack('Nhigh/Nlow', substr($bytes, $offset + 8, 8));
                if ($large['high'] !== 0) {
                    $this->fail('Contenedor de imagen demasiado grande.');
                }$size = $large['low'];
                $header = 16;
            }
            if ($size === 0) {
                $size = $length - $offset;
            }
            if ($size < $header || $size > $length - $offset) {
                $this->fail('Contenedor de imagen no válido.');
            }
            $types[] = $type;
            if ($type === 'ftyp') {
                $brands = substr($bytes, $offset + $header, $size - $header);
            }
            $offset += $size;
        }
        if (! in_array('ftyp', $types, true) || ! in_array('meta', $types, true) || ! in_array('mdat', $types, true) || ! preg_match('/heic|heix|hevc|hevx|heim|heis|hevm|hevs|mif1/', $brands)) {
            $this->fail('La imagen HEIC/HEIF no contiene la estructura esperada.');
        }
    }

    private function checkZip(string $path, string $extension, int $depth): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->fail('La validación de documentos comprimidos no está disponible. Consulte con soporte.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            $this->fail('El documento comprimido está dañado.');
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > config('visit_evidence.zip_max_entries')) {
                $this->fail('El comprimido contiene demasiados archivos.');
            }
            $total = 0;
            $seen = [];
            $office = $extension !== 'zip';
            $main = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$extension] ?? null;
            $contentTypes = ['docx' => 'wordprocessingml.document.main+xml', 'xlsx' => 'spreadsheetml.sheet.main+xml', 'pptx' => 'presentationml.presentation.main+xml'];
            if ($office) {
                foreach (['[Content_Types].xml', '_rels/.rels', $main] as $required) {
                    if ($zip->locateName($required) === false) {
                        $this->fail('El documento Office no tiene la estructura esperada.');
                    }
                }
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! $stat) {
                    $this->fail('No se pudo inspeccionar el comprimido.');
                }
                $name = $stat['name'];
                $parts = explode('/', $name);
                if (! mb_check_encoding($name, 'UTF-8') || str_contains($name, chr(92)) || preg_match('/[\x00-\x1f\x7f:]/', $name) || str_starts_with($name, '/') || in_array('..', $parts, true) || in_array('.', $parts, true) || isset($seen[mb_strtolower($name)])) {
                    $this->fail('El comprimido contiene rutas no permitidas o duplicadas.');
                }
                $seen[mb_strtolower($name)] = true;
                $os = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $os, $attributes) && (($attributes >> 16) & 0170000) === 0120000) {
                    $this->fail('No se permiten enlaces simbólicos en comprimidos.');
                }
                if (($stat['encryption_method'] ?? 0) !== 0) {
                    $this->fail('No se permiten documentos cifrados.');
                }
                $size = $stat['size'];
                $compressed = $stat['comp_size'];
                $total += $size;
                if ($size > config('visit_evidence.max_file_bytes') || $total > config('visit_evidence.zip_max_expanded_bytes') || ($size > 0 && $size / max(1, $compressed) > config('visit_evidence.zip_max_ratio'))) {
                    $this->fail('El comprimido supera los límites de tamaño o expansión.');
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (! $this->allowedName($name) || preg_match('~vbaProject|macroEnabled|activeX|embeddings/|externalLinks/|connections\.xml~i', $name)) {
                    $this->fail('El documento contiene macros, objetos o contenido activo no permitido.');
                }
                $data = $zip->getFromIndex($i);
                if (! is_string($data) || strlen($data) !== $size) {
                    $this->fail('No se pudo verificar un archivo del comprimido.');
                }
                if (preg_match('/<\?(?:php|=)|<script\b|<html\b/i', $data)) {
                    $this->fail('El comprimido contiene scripts o HTML no permitidos.');
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($office) {
                    if (in_array($ext, ['xml', 'rels'], true)) {
                        $this->checkXml($data);
                        if ($name === '[Content_Types].xml' && (! str_contains($data, $contentTypes[$extension]) || preg_match('/macroEnabled|vbaProject|activeX|oleObject/i', $data))) {
                            $this->fail('El tipo real del documento Office no coincide.');
                        }
                        if (preg_match('/TargetMode\s*=\s*[\'"]External[\'"]|<script\b|DDEAUTO|<ddeLink\b/i', $data)) {
                            $this->fail('No se permiten relaciones externas ni contenido activo en documentos Office.');
                        }
                    } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $this->inspectBytes($data, $ext, $depth + 1);
                    } elseif (! in_array($ext, ['gif', 'bmp', 'tif', 'tiff', 'ttf', 'odttf'], true) && ! ($ext === 'bin' && preg_match('~^(?:word|xl)/printerSettings/~', $name))) {
                        $this->fail('El documento contiene objetos no admitidos.');
                    }
                } else {
                    if (! in_array($ext, self::EXTENSIONS, true) || $ext === 'zip') {
                        $this->fail('El ZIP solo puede contener imágenes y documentos permitidos, sin otros ZIP.');
                    }
                    $this->inspectBytes($data, $ext, $depth + 1);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function checkXml(string $data): void
    {
        if (strlen($data) > 2097152) {
            $this->fail('El XML del documento supera el límite de inspección segura.');
        }
        if (! class_exists(\DOMDocument::class) || preg_match('/<!DOCTYPE|<!ENTITY/i', $data)) {
            $this->fail('El documento XML contiene elementos no permitidos.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            if (! $dom->loadXML($data, LIBXML_NONET)) {
                $this->fail('La estructura XML del documento no es válida.');
            }
            if ($dom->doctype !== null) {
                $this->fail('El documento XML contiene elementos no permitidos.');
            }
            // Inspeccionar valores ya decodificados impide eludir las comprobaciones
            // mediante UTF-16 o referencias numéricas dentro de atributos XML.
            foreach ($dom->getElementsByTagName('*') as $element) {
                if (in_array(strtolower($element->localName), ['script', 'ddelink'], true)) {
                    $this->fail('El documento contiene contenido activo no permitido.');
                }
                foreach ($element->attributes as $attribute) {
                    if ((strtolower($attribute->localName) === 'targetmode' && strtolower(trim($attribute->value)) === 'external')
                        || preg_match('/macroEnabled|vbaProject|activeX|oleObject/i', $attribute->value)) {
                        $this->fail('No se permiten relaciones externas ni contenido activo en documentos Office.');
                    }
                }
            }
            if (stripos($dom->textContent, 'DDEAUTO') !== false) {
                $this->fail('El documento contiene contenido activo no permitido.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function inspectBytes(string $bytes, string $extension, int $depth): void
    {
        $temporary = tmpfile();
        if (! $temporary) {
            $this->fail('No se pudo comprobar el documento.');
        }
        try {
            if (fwrite($temporary, $bytes) !== strlen($bytes)) {
                $this->fail('No se pudo comprobar el documento.');
            }
            fflush($temporary);
            $path = stream_get_meta_data($temporary)['uri'];
            $this->inspectPath($path, $extension, $depth);
        } finally {
            fclose($temporary);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['evidence' => $message]);
    }
}
