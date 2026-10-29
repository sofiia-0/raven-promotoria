<?php

namespace App\Services;

use App\Models\PromoterReasonCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PromoterReasonCategories
{
    public const DOMAINS = ['visit_objective', 'request_alta', 'request_baja'];

    public function available(): bool
    {
        return Schema::hasTable('promoter_reason_categories');
    }

    public function options(string $domain): Collection
    {
        return $this->available() && in_array($domain, self::DOMAINS, true)
            ? PromoterReasonCategory::query()->available($domain)->get() : collect();
    }

    /** Mantiene las reglas históricas si todavía no hay opciones disponibles. */
    public function requestExplanationRules(string $domain): array
    {
        return [$this->options($domain)->isNotEmpty() ? 'nullable' : 'required', 'string', 'min:10', 'max:1500'];
    }

    public function resolveRequest(string $domain, mixed $id, string $explanation, string $field, string $explanationField): ?array
    {
        if (! in_array($domain, ['request_alta', 'request_baja'], true)) {
            $this->fail($field, 'Seleccione un catálogo de solicitudes válido.');
        }
        if (($id === null || $id === '') && $this->options($domain)->isNotEmpty()) {
            $this->fail($field, 'Seleccione la categoría del motivo de su solicitud.');
        }

        return $this->resolve($domain, $id, $explanation, $field, $explanationField);
    }

    public function resolve(string $domain, mixed $id, string $explanation, string $field = 'reasonCategoryId', string $explanationField = 'reason'): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (! in_array($domain, self::DOMAINS, true) || ! $this->available() || (! is_string($id) && ! is_int($id)) || ! ctype_digit((string) $id)) {
            $this->fail($field, 'Seleccione una opción disponible del catálogo correspondiente.');
        }
        $category = PromoterReasonCategory::query()->available($domain)->whereKey((int) $id)->first();
        if (! $category) {
            $this->fail($field, 'La opción no está disponible en este catálogo. Seleccione otra.');
        }
        $explanation = trim($explanation);
        if (mb_strlen($explanation) > 1500 || ($category->is_other && mb_strlen($explanation) < 10)) {
            $this->fail($explanationField, 'Al seleccionar Otro, explique el motivo con al menos 10 caracteres y hasta 1500.');
        }

        return ['id' => $category->id, 'domain' => $domain, 'code' => $category->code, 'label' => $category->name, 'is_provisional' => $category->is_provisional];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
