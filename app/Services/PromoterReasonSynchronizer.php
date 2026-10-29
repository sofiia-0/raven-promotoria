<?php

namespace App\Services;

use App\Models\PromoterReasonCategory;
use App\Models\VisitReason;
use App\Support\EstablishmentSearch;
use Database\Seeders\VisitCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Inserta propuestas ausentes; nunca actualiza ni reactiva registros existentes. */
class PromoterReasonSynchronizer
{
    private function normalized(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', EstablishmentSearch::normalize($name)));
    }

    public function preview(string $scope = 'unperformed', bool $lock = false): array
    {
        if (! in_array($scope, ['unperformed', 'categories', 'all'], true)) {
            throw new \InvalidArgumentException('Unknown catalog scope.');
        }
        $rows = [];
        $conflicts = [];
        if ($scope !== 'categories') {
            $query = VisitReason::query()->orderBy('id');
            $existing = ($lock ? $query->lockForUpdate() : $query)->get()->groupBy(fn ($r) => $this->normalized($r->name));
            foreach (VisitCatalogSeeder::REASONS as $i => $name) {
                $matches = $existing->get($this->normalized($name), collect());
                if ($matches->count() > 1) {
                    $conflicts[] = 'Hay motivos equivalentes con distintos IDs; requieren revisión.';
                }
                $rows[] = ['domain' => 'unperformed', 'name' => $name, 'sort_order' => ($i + 1) * 10, 'action' => $matches->isEmpty() ? 'create' : 'preserve', 'existing_ids' => $matches->pluck('id')->all(), 'provisional' => true];
            }
        }
        if ($scope !== 'unperformed') {
            if (! (new PromoterReasonCategories)->available()) {
                $conflicts[] = 'La migración de categorías todavía no está aplicada.';
            } else {
                foreach (PromoterReasonCategories::DOMAINS as $domain) {
                    $query = PromoterReasonCategory::where('domain', $domain)->orderBy('id');
                    $existing = ($lock ? $query->lockForUpdate() : $query)->get();
                    foreach (config('promoter_reasons.'.$domain, []) as $i => $proposal) {
                        $matches = $existing->filter(fn ($r) => $r->code === $proposal['code'] || $this->normalized($r->name) === $this->normalized($proposal['name']));
                        if ($matches->count() > 1 || ($matches->isNotEmpty() && $matches->first()->code !== $proposal['code'])) {
                            $conflicts[] = 'Hay categorías equivalentes o códigos incompatibles; requieren revisión.';
                        }
                        $rows[] = ['domain' => $domain, ...$proposal, 'sort_order' => ($i + 1) * 10, 'action' => $matches->isEmpty() ? 'create' : 'preserve', 'existing_ids' => $matches->pluck('id')->all(), 'provisional' => true];
                    }
                }
            }
        }
        $snapshot = ['scope' => $scope, 'rows' => $rows, 'conflicts' => array_values(array_unique($conflicts))];
        $snapshot['fingerprint'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));

        return $snapshot;
    }

    public function apply(string $scope, string $expected): array
    {
        return DB::transaction(function () use ($scope, $expected) {
            $plan = $this->preview($scope, true);
            if ($plan['conflicts'] || ! hash_equals($plan['fingerprint'], $expected)) {
                throw ValidationException::withMessages(['catalog' => 'El catálogo cambió o presenta conflictos. Revise una nueva simulación.']);
            }
            $created = 0;
            foreach ($plan['rows'] as $row) {
                if ($row['action'] !== 'create') {
                    continue;
                }
                if ($row['domain'] === 'unperformed') {
                    VisitReason::create(['name' => $row['name'], 'sort_order' => $row['sort_order'], 'is_active' => true]);
                } else {
                    PromoterReasonCategory::create(['domain' => $row['domain'], 'code' => $row['code'], 'name' => $row['name'], 'is_other' => $row['is_other'] ?? false, 'sort_order' => $row['sort_order'], 'is_active' => true, 'is_provisional' => true]);
                }
                $created++;
            }

            return ['created' => $created, 'preserved' => count($plan['rows']) - $created, 'provisional' => true];
        });
    }
}
