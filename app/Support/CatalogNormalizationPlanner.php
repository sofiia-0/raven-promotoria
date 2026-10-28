<?php

namespace App\Support;

use InvalidArgumentException;

/** Planes y respaldos en memoria: no consulta ni escribe en una base de datos. */
class CatalogNormalizationPlanner
{
    public const FIELDS = ['name', 'client_type', 'category', 'neighborhood', 'address', 'city', 'department', 'frequency', 'dependents', 'shifts', 'phone', 'email', 'source_responsible'];

    private const NUMBERS = ['frequency', 'dependents', 'shifts'];

    public function __construct(private readonly array $geography) {}

    public function baseline(array $row): array
    {
        foreach (['source_reference', ...self::FIELDS] as $field) {
            if (! array_key_exists($field, $row)) {
                throw new InvalidArgumentException('Falta un campo de la base de importación: '.$field);
            }
        }
        $reference = trim((string) $row['source_reference']);
        if (! preg_match('/^REV-\d{4}$/', $reference)) {
            throw new InvalidArgumentException('Referencia original no válida.');
        }
        $result = ['source_reference' => $reference];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if (in_array($field, self::NUMBERS, true)) {
                if ($value !== '' && ! ctype_digit($value)) {
                    throw new InvalidArgumentException('Valor numérico de origen no válido.');
                }
                $value = $value === '' ? null : (int) $value;
            } elseif (! in_array($field, ['name', 'source_responsible'], true) && $value === '') {
                $value = null;
            }
            $result[$field] = $value;
        }
        $result['is_active'] = true;

        return $result;
    }

    public function candidate(array $original): array
    {
        return $this->review($this->baseline($original));
    }

    /** Una fotografía de auditoría parcial nunca sirve para respaldar ni autorizar escrituras. */
    public function auditPreview(array $auditRows, array $scope): array
    {
        if ($scope === [] || count($scope) !== count(array_unique($scope))) {
            throw new InvalidArgumentException('El alcance debe ser explícito y sin referencias repetidas.');
        }
        $index = $this->index($auditRows);
        $records = [];
        foreach ($scope as $reference) {
            if (count($index[$reference] ?? []) !== 1) {
                throw new InvalidArgumentException('La referencia no es única en la auditoría.');
            }
            $row = [];
            foreach (['source_reference', 'name', 'department', 'city', 'neighborhood', 'address'] as $field) {
                if (! array_key_exists($field, $index[$reference][0])) {
                    throw new InvalidArgumentException('La auditoría no contiene el campo '.$field);
                }
                $row[$field] = $index[$reference][0][$field];
            }
            $record = $this->review($row);
            if ($record['status'] !== 'manual_review') {
                $record['status'] = 'pending_original_csv_and_current_snapshot';
            }
            $records[] = $record;
        }

        return $this->seal(['kind' => 'raven.normalization.audit-preview.v1', 'audit_sha256' => $this->hash($auditRows), 'geography_sha256' => $this->hash($this->geography), 'scope' => array_values($scope), 'excluded_count' => count($auditRows) - count($scope), 'records' => $records]);
    }

    private function review(array $row): array
    {
        $parts = explode('·', $row['name']);
        $name = trim(array_shift($parts));
        $details = [];
        $manual = $name === '' || $parts === [];
        $rule = null;
        $department = $this->key($row['department']);
        $city = $this->key($row['city']);
        $municipalities = [];
        foreach ($this->geography as $dep => $cities) {
            if ($this->key($dep) === $department) {
                $municipalities = array_map($this->key(...), $cities);
            }
        }
        if (! in_array($city, $municipalities, true)) {
            $manual = true;
        }
        foreach ($parts as $part) {
            $fragment = trim($part);
            $key = $this->key($fragment);
            $isDepartment = $key !== '' && $key === $department;
            $isCity = $key !== '' && $key === $city;
            $conflictingCity = $isDepartment && in_array($key, $municipalities, true) && ! $isCity;
            if (($isDepartment || $isCity) && ! $conflictingCity) {
                $kind = $isDepartment && $isCity ? 'redundant_department_and_municipality' : ($isDepartment ? 'redundant_department' : 'redundant_municipality');
                $rule ??= $kind;
                if ($rule !== $kind) {
                    $rule = 'redundant_geography_multiple';
                }
                $details[] = ['fragment' => $fragment, 'kind' => $kind, 'address_action' => 'do_not_repeat_geography'];
            } else {
                $manual = true;
                $inAddress = $this->containsFragment((string) $row['address'], $fragment);
                $inNeighborhood = $key !== '' && $key === $this->key($row['neighborhood']);
                $details[] = ['fragment' => $fragment, 'kind' => $inNeighborhood ? 'location_context_requires_approval' : 'meaning_requires_approval', 'address_action' => $inAddress ? 'already_in_address_do_not_duplicate' : ($inNeighborhood ? 'review_location_before_appending' : 'do_not_append_without_confirming_meaning')];
            }
        }
        $beforeReference = EstablishmentPresentation::reference($row['name'], $row['department'], $row['city']);
        $afterReference = EstablishmentPresentation::reference($name, $row['department'], $row['city']);
        if ($beforeReference !== $afterReference
            || EstablishmentPresentation::title($row['name'], $row['department']) !== EstablishmentPresentation::title($name, $row['department'])) {
            $manual = true;
        }

        return ['source_reference' => $row['source_reference'], 'rule' => $rule, 'status' => $manual ? 'manual_review' : 'candidate', 'before' => $row, 'changes' => $manual ? [] : ['name' => $name], 'fragments' => $details, 'branch_before' => $beforeReference, 'branch_after' => $manual ? $beforeReference : $afterReference];
    }

    /** currentRows = null significa que no se consultó una fotografía actual. */
    public function preview(array $baselineRows, array $scope, ?array $currentRows = null): array
    {
        if ($scope === [] || count($scope) !== count(array_unique($scope))) {
            throw new InvalidArgumentException('El alcance debe ser explícito, no vacío y sin referencias repetidas.');
        }
        $base = $this->index($baselineRows);
        foreach ($base as $reference => $rows) {
            if (count($rows) !== 1) {
                throw new InvalidArgumentException('Referencia original duplicada: '.$reference);
            }
        }
        $current = $currentRows === null ? null : $this->index($currentRows);
        $records = [];
        foreach ($scope as $reference) {
            if (! isset($base[$reference])) {
                throw new InvalidArgumentException('Referencia fuera de la base original: '.$reference);
            }
            $record = $this->candidate($base[$reference][0]);
            if ($record['status'] !== 'manual_review') {
                if ($current === null) {
                    $record['status'] = 'pending_current_snapshot';
                } else {
                    $matches = $current[$reference] ?? [];
                    if (count($matches) !== 1) {
                        $record['status'] = count($matches) ? 'conflict_duplicate_reference' : 'conflict_missing_reference';
                    } else {
                        $snapshot = $this->snapshot($matches[0]);
                        if ($snapshot === null) {
                            $record['status'] = 'conflict_incomplete_snapshot';
                        } else {
                            $conflicts = $this->differences($record['before'], $snapshot, ['name']);
                            $record['conflicting_fields'] = $conflicts;
                            if ($conflicts !== []) {
                                $record['status'] = 'conflict_post_import_changes';
                            } elseif ($snapshot['name'] === $record['changes']['name']) {
                                $record['status'] = 'already_matches_proposal';
                            } elseif ($snapshot['name'] !== $record['before']['name']) {
                                $record['status'] = 'conflict_post_import_changes';
                                $record['conflicting_fields'] = ['name'];
                            } else {
                                $record['status'] = 'eligible_for_authorization';
                                $record['expected_current'] = $snapshot;
                            }
                        }
                    }
                }
            }
            $records[] = $record;
        }

        return $this->seal(['kind' => 'raven.normalization.preview.v1', 'baseline_sha256' => $this->hash($baselineRows), 'geography_sha256' => $this->hash($this->geography), 'scope' => array_values($scope), 'excluded_count' => count($baselineRows) - count($scope), 'records' => $records]);
    }

    /** Solo prepara el contenido del respaldo; la autorización debe fijar su SHA-256 fuera del archivo. */
    public function backup(array $plan, array $approvedReferences): array
    {
        $this->verify($plan, 'raven.normalization.preview.v1');
        if ($plan['geography_sha256'] !== $this->hash($this->geography)) {
            throw new InvalidArgumentException('El catálogo geográfico cambió; genere una nueva revisión.');
        }
        if ($approvedReferences === [] || count($approvedReferences) !== count(array_unique($approvedReferences))) {
            throw new InvalidArgumentException('Indique referencias aprobadas únicas.');
        }
        $records = [];
        foreach ($approvedReferences as $reference) {
            $matches = array_values(array_filter($plan['records'], fn ($row) => $row['source_reference'] === $reference));
            if (count($matches) !== 1 || $matches[0]['status'] !== 'eligible_for_authorization') {
                throw new InvalidArgumentException('La referencia no es elegible con una fotografía actual: '.$reference);
            }
            $row = $matches[0];
            if (array_keys($row['changes']) !== ['name']) {
                throw new InvalidArgumentException('Esta etapa solo permite cambiar name.');
            }
            $records[] = ['source_reference' => $reference, 'before' => $row['expected_current'], 'changes' => $row['changes']];
        }

        return $this->seal(['kind' => 'raven.normalization.backup.v1', 'plan_sha256' => $plan['sha256'], 'geography_sha256' => $plan['geography_sha256'], 'records' => $records]);
    }

    /** Registra únicamente una fotografía posterior que coincida con el parche previsto. */
    public function receipt(array $backup, array $postRows): array
    {
        $this->verify($backup, 'raven.normalization.backup.v1');
        $current = $this->index($postRows);
        $records = [];
        foreach ($backup['records'] as $row) {
            $matches = $current[$row['source_reference']] ?? [];
            $after = count($matches) === 1 ? $this->snapshot($matches[0]) : null;
            $expected = array_replace($row['before'], $row['changes']);
            if ($after === null || $this->differences($expected, $after, ['updated_at']) !== []) {
                throw new InvalidArgumentException('La fotografía posterior no coincide con el cambio respaldado.');
            }
            $records[] = ['source_reference' => $row['source_reference'], 'before' => $row['before'], 'after' => $after];
        }

        return $this->seal(['kind' => 'raven.normalization.receipt.v1', 'backup_sha256' => $backup['sha256'], 'records' => $records]);
    }

    public function rollbackPreview(array $receipt, array $currentRows): array
    {
        $this->verify($receipt, 'raven.normalization.receipt.v1');
        $current = $this->index($currentRows);
        $records = [];
        foreach ($receipt['records'] as $row) {
            $matches = $current[$row['source_reference']] ?? [];
            $now = count($matches) === 1 ? $this->snapshot($matches[0]) : null;
            $record = ['source_reference' => $row['source_reference'], 'status' => 'conflict_rollback', 'changes' => []];
            if ($now !== null && $this->differences($row['before'], $now, ['updated_at']) === []) {
                $record['status'] = 'already_matches_original';
            } elseif ($now !== null && $this->differences($row['after'], $now) === []) {
                $record['status'] = 'eligible_for_rollback_authorization';
                $record['expected_current'] = $now;
                $record['changes'] = ['name' => $row['before']['name']];
            }
            $records[] = $record;
        }

        return $this->seal(['kind' => 'raven.normalization.rollback-preview.v1', 'receipt_sha256' => $receipt['sha256'], 'records' => $records]);
    }

    private function snapshot(array $row): ?array
    {
        foreach (['source_reference', ...self::FIELDS, 'is_active', 'id', 'updated_at'] as $key) {
            if (! array_key_exists($key, $row)) {
                return null;
            }
        }
        if (! is_int($row['id']) || $row['id'] < 1 || ! is_string($row['updated_at']) || $row['updated_at'] === '') {
            return null;
        }
        $result = array_intersect_key($row, array_flip(['source_reference', ...self::FIELDS, 'is_active', 'id', 'updated_at']));
        foreach (self::NUMBERS as $field) {
            if ($result[$field] !== null && preg_match('/^\d+$/', (string) $result[$field])) {
                $result[$field] = (int) $result[$field];
            }
        }
        $result['is_active'] = in_array($result['is_active'], [true, 1, '1'], true);

        return $result;
    }

    private function differences(array $expected, array $actual, array $except = []): array
    {
        return array_values(array_filter(array_keys($expected), fn ($field) => ! in_array($field, $except, true) && (! array_key_exists($field, $actual) || $expected[$field] !== $actual[$field])));
    }

    private function index(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(string) ($row['source_reference'] ?? '')][] = $row;
        }

        return $result;
    }

    private function key(?string $value): string
    {
        return EstablishmentSearch::normalize(trim((string) $value));
    }

    private function containsFragment(string $address, string $fragment): bool
    {
        return $fragment !== '' && preg_match('/(?<![a-z0-9])'.preg_quote($this->key($fragment), '/').'(?![a-z0-9])/u', $this->key($address)) === 1;
    }

    private function hash(array $value): string
    {
        $sort = function (array $array) use (&$sort): array {
            if (! array_is_list($array)) {
                ksort($array);
            }
            foreach ($array as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }

            return $array;
        };

        return hash('sha256', json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function seal(array $value): array
    {
        $value['sha256'] = $this->hash($value);

        return $value;
    }

    private function verify(array $value, string $kind): void
    {
        $hash = $value['sha256'] ?? '';
        unset($value['sha256']);
        if (($value['kind'] ?? null) !== $kind || ! hash_equals($this->hash($value), $hash)) {
            throw new InvalidArgumentException('El archivo de revisión o respaldo fue alterado o no es compatible.');
        }
    }
}
