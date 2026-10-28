<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\User;
use App\Support\CatalogNormalizationPlanner;
use App\Support\EstablishmentSearch;
use App\Support\NicaraguaGeography;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class CatalogNormalizationPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Normalization tests require isolated SQLite :memory:.');
        }
    }

    private function planner(): CatalogNormalizationPlanner
    {
        return new CatalogNormalizationPlanner(NicaraguaGeography::catalog()['departments']);
    }

    private function original(array $changes = []): array
    {
        return array_replace(['source_reference' => 'REV-0001', 'name' => 'Ensayo 7 · León', 'client_type' => 'Tipo ficticio', 'category' => 'A', 'department' => 'León', 'city' => 'León', 'neighborhood' => 'Barrio de prueba', 'address' => 'Frente al parque de prueba.', 'frequency' => '2', 'dependents' => '1', 'shifts' => '1', 'phone' => '', 'email' => '', 'source_responsible' => 'Responsable ficticia'], $changes);
    }

    private function current(array $original, array $changes = []): array
    {
        return array_replace($this->planner()->baseline($original), ['id' => 10, 'updated_at' => '2026-10-28 10:00:00'], $changes);
    }

    private function record(array $original): Pharmacy
    {
        $pharmacy = Pharmacy::create($this->planner()->baseline($original));
        DB::table('pharmacies')->where('id', $pharmacy->id)->update(['updated_at' => '2026-10-28 10:00:00']);

        return $pharmacy;
    }

    private function rows(): array
    {
        return DB::table('pharmacies')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    /** Adaptador exclusivo de pruebas; no existe un ejecutor de escrituras en producción. */
    private function applyFixture(array $record, string $time): int
    {
        return DB::transaction(function () use ($record, $time) {
            $query = DB::table('pharmacies');
            foreach ($record['expected_current'] as $field => $value) {
                $value === null ? $query->whereNull($field) : $query->where($field, $value);
            }

            return $query->update(array_replace($record['changes'], ['updated_at' => $time]));
        });
    }

    public function test_csv_only_preview_never_becomes_eligible_without_current_snapshot(): void
    {
        $plan = $this->planner()->preview([$this->original()], ['REV-0001']);
        $row = $plan['records'][0];
        $this->assertSame('pending_current_snapshot', $row['status']);
        $this->assertSame('redundant_department_and_municipality', $row['rule']);
        $this->assertSame(['name' => 'Ensayo 7'], $row['changes']);
        $this->assertSame('Frente al parque de prueba.', $row['before']['address']);
        $this->assertSame('', $row['branch_before']);
        $this->assertSame('', $row['branch_after']);
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->backup($plan, ['REV-0001']);
    }

    public function test_municipal_redundancy_preserves_name_number_and_address_even_if_not_repeated_there(): void
    {
        $row = $this->planner()->candidate($this->original(['name' => 'Ensayo 12 · Chichigalpa', 'department' => 'Chinandega', 'city' => 'Chichigalpa']));
        $this->assertSame('candidate', $row['status']);
        $this->assertSame('redundant_municipality', $row['rule']);
        $this->assertSame(['name' => 'Ensayo 12'], $row['changes']);
        $this->assertSame('do_not_repeat_geography', $row['fragments'][0]['address_action']);
    }

    public function test_unknown_details_require_approval_and_are_never_appended_to_an_address(): void
    {
        foreach (['Barrio Central', 'Sucursal 2', 'Torre 4', 'Local 8', 'Walmart', '1'] as $fragment) {
            $row = $this->planner()->candidate($this->original(['name' => 'Ensayo · '.$fragment]));
            $this->assertSame('manual_review', $row['status']);
            $this->assertSame([], $row['changes']);
            $this->assertSame($fragment, $row['fragments'][0]['fragment']);
            $this->assertSame('Frente al parque de prueba.', $row['before']['address']);
            $this->assertSame($fragment, $row['branch_after']);
        }
    }

    public function test_existing_specific_detail_is_recognized_without_duplication_or_assuming_its_meaning(): void
    {
        $row = $this->planner()->candidate($this->original(['name' => 'Ensayo · Barrio Central', 'address' => 'En Barrio Central, frente al parque.']));
        $this->assertSame('manual_review', $row['status']);
        $this->assertSame('already_in_address_do_not_duplicate', $row['fragments'][0]['address_action']);
        $this->assertSame([], $row['changes']);
        $row = $this->planner()->candidate($this->original(['name' => 'Ensayo · Sur', 'address' => 'Edificio Suramérica.']));
        $this->assertSame('do_not_append_without_confirming_meaning', $row['fragments'][0]['address_action']);
    }

    public function test_geographic_conflicts_and_unknown_pairs_are_manual(): void
    {
        foreach ([['name' => 'Ensayo · Ticuantepe', 'department' => 'Managua', 'city' => 'Managua'], ['department' => 'Masaya', 'city' => 'Managua']] as $values) {
            $row = $this->planner()->candidate($this->original($values));
            $this->assertSame('manual_review', $row['status']);
            $this->assertSame([], $row['changes']);
        }
    }

    public function test_scope_excludes_other_records_and_cannot_approve_them(): void
    {
        $one = $this->original();
        $two = $this->original(['source_reference' => 'REV-0002', 'name' => 'Ensayo · Sucursal 1']);
        $plan = $this->planner()->preview([$one, $two], ['REV-0001'], [$this->current($one)]);
        $this->assertSame(1, $plan['excluded_count']);
        $this->assertCount(1, $plan['records']);
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->backup($plan, ['REV-0002']);
    }

    public function test_duplicate_baseline_references_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->preview([$this->original(), $this->original()], ['REV-0001']);
    }

    public function test_repeated_or_unknown_scope_is_rejected(): void
    {
        foreach ([['REV-0001', 'REV-0001'], ['REV-9999'], []] as $scope) {
            try {
                $this->planner()->preview([$this->original()], $scope);
                $this->fail('Invalid scope accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_missing_duplicate_and_incomplete_current_rows_are_conflicts(): void
    {
        $base = $this->original();
        $current = $this->current($base);
        foreach ([[[], 'conflict_missing_reference'], [[$current, $current], 'conflict_duplicate_reference'], [[array_diff_key($current, ['updated_at' => true])], 'conflict_incomplete_snapshot']] as [$rows, $status]) {
            $plan = $this->planner()->preview([$base], ['REV-0001'], $rows);
            $this->assertSame($status, $plan['records'][0]['status']);
        }
    }

    public function test_any_post_import_edit_is_preserved_and_inactive_records_are_not_eligible(): void
    {
        $base = $this->original();
        foreach (['name' => 'Nombre editado', 'address' => 'Dirección editada', 'phone' => '0000-0000', 'category' => 'AA', 'is_active' => false] as $field => $value) {
            $plan = $this->planner()->preview([$base], ['REV-0001'], [$this->current($base, [$field => $value])]);
            $this->assertSame('conflict_post_import_changes', $plan['records'][0]['status']);
            $this->assertContains($field, $plan['records'][0]['conflicting_fields']);
        }
    }

    public function test_already_matching_proposal_is_noop_but_does_not_hide_other_edits(): void
    {
        $base = $this->original();
        $current = $this->current($base, ['name' => 'Ensayo 7']);
        $this->assertSame('already_matches_proposal', $this->planner()->preview([$base], ['REV-0001'], [$current])['records'][0]['status']);
        $current['phone'] = '0000-0000';
        $this->assertSame('conflict_post_import_changes', $this->planner()->preview([$base], ['REV-0001'], [$current])['records'][0]['status']);
    }

    public function test_tampered_plan_backup_or_receipt_is_rejected(): void
    {
        $base = $this->original();
        $current = $this->current($base);
        $plan = $this->planner()->preview([$base], ['REV-0001'], [$current]);
        $plan['records'][0]['changes']['address'] = 'No permitido';
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->backup($plan, ['REV-0001']);
    }

    public function test_backup_and_receipt_roundtrip_guard_reversal_and_are_idempotent(): void
    {
        $base = $this->original();
        $before = $this->current($base);
        $plan = $this->planner()->preview([$base], ['REV-0001'], [$before]);
        $backup = json_decode(json_encode($this->planner()->backup($plan, ['REV-0001']), JSON_THROW_ON_ERROR), true);
        $after = array_replace($before, ['name' => 'Ensayo 7', 'updated_at' => '2026-10-28 10:00:01']);
        $receipt = json_decode(json_encode($this->planner()->receipt($backup, [$after]), JSON_THROW_ON_ERROR), true);
        $reverse = $this->planner()->rollbackPreview($receipt, [$after]);
        $this->assertSame('eligible_for_rollback_authorization', $reverse['records'][0]['status']);
        $this->assertSame(['name' => $base['name']], $reverse['records'][0]['changes']);
        $restored = array_replace($before, ['updated_at' => '2026-10-28 10:00:02']);
        $this->assertSame('already_matches_original', $this->planner()->rollbackPreview($receipt, [$restored])['records'][0]['status']);
        foreach ([['phone' => '0000-0000'], ['updated_at' => '2026-10-28 10:00:03'], ['id' => 99]] as $edit) {
            $this->assertSame('conflict_rollback', $this->planner()->rollbackPreview($receipt, [array_replace($after, $edit)])['records'][0]['status']);
        }
        $receipt['records'][0]['before']['name'] = 'Alterado';
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->rollbackPreview($receipt, [$after]);
    }

    public function test_post_snapshot_receipt_rejects_an_unplanned_change(): void
    {
        $base = $this->original();
        $before = $this->current($base);
        $backup = $this->planner()->backup($this->planner()->preview([$base], ['REV-0001'], [$before]), ['REV-0001']);
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->receipt($backup, [array_replace($before, ['name' => 'Ensayo 7', 'address' => 'Cambio no previsto'])]);
    }

    public function test_fixture_apply_and_reversal_preserve_shared_assignments_histories_and_out_of_scope_rows(): void
    {
        $one = $this->original();
        $two = $this->original(['source_reference' => 'REV-0002', 'name' => 'Ensayo · Sucursal 1']);
        $pharmacy = $this->record($one);
        $other = $this->record($two);
        foreach ([1, 2] as $i) {
            $actor = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
            PharmacyAssignment::create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $actor->id, 'assigned_at' => now()]);
        }
        PharmacyChange::create(['pharmacy_id' => $pharmacy->id, 'actor_id' => $actor->id, 'before' => ['phone' => null], 'after' => ['phone' => null]]);
        $relations = [DB::table('pharmacy_assignments')->get()->toJson(), DB::table('pharmacy_changes')->get()->toJson()];
        $untouched = $other->fresh()->getAttributes();
        $plan = $this->planner()->preview([$one, $two], ['REV-0001'], $this->rows());
        $backup = $this->planner()->backup($plan, ['REV-0001']);
        $this->assertSame(1, $this->applyFixture($plan['records'][0], '2026-10-28 10:00:01'));
        $this->assertSame(0, $this->applyFixture($plan['records'][0], '2026-10-28 10:00:01'));
        $this->assertSame('already_matches_proposal', $this->planner()->preview([$one, $two], ['REV-0001'], $this->rows())['records'][0]['status']);
        $receipt = $this->planner()->receipt($backup, $this->rows());
        $reverse = $this->planner()->rollbackPreview($receipt, $this->rows());
        $this->assertSame(1, $this->applyFixture($reverse['records'][0], '2026-10-28 10:00:02'));
        $this->assertSame(0, $this->applyFixture($reverse['records'][0], '2026-10-28 10:00:02'));
        $this->assertSame($one['name'], $pharmacy->fresh()->name);
        $this->assertSame($untouched, $other->fresh()->getAttributes());
        $this->assertSame($relations, [DB::table('pharmacy_assignments')->get()->toJson(), DB::table('pharmacy_changes')->get()->toJson()]);
        $this->assertSame(2, Pharmacy::count());
        $this->assertSame($pharmacy->id, $pharmacy->fresh()->id);
    }

    public function test_late_edit_after_preview_prevents_fixture_update(): void
    {
        $base = $this->original();
        $pharmacy = $this->record($base);
        $plan = $this->planner()->preview([$base], ['REV-0001'], $this->rows());
        DB::table('pharmacies')->where('id', $pharmacy->id)->update(['phone' => '0000-0000', 'updated_at' => '2026-10-28 10:00:01']);
        $this->assertSame(0, $this->applyFixture($plan['records'][0], '2026-10-28 10:00:02'));
        $this->assertSame($base['name'], $pharmacy->fresh()->name);
        $this->assertSame('0000-0000', $pharmacy->fresh()->phone);
    }

    public function test_searching_the_display_title_and_number_still_works_after_normalization(): void
    {
        $base = $this->original();
        $pharmacy = $this->record($base);
        $actor = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
        PharmacyAssignment::create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $actor->id, 'assigned_at' => now()]);
        $title = $pharmacy->display_name;
        $this->assertSame(1, EstablishmentSearch::forPromoter($actor, $title)->count());
        $plan = $this->planner()->preview([$base], ['REV-0001'], $this->rows());
        $this->assertSame(1, $this->applyFixture($plan['records'][0], '2026-10-28 10:00:01'));
        $this->assertSame('Ensayo 7', $pharmacy->fresh()->name);
        $this->assertSame($title, $pharmacy->fresh()->display_name);
        $this->assertSame('', $pharmacy->fresh()->branch_summary);
        $this->assertSame(1, EstablishmentSearch::forPromoter($actor, $title)->count());
        $this->assertSame(1, EstablishmentSearch::forPromoter($actor, 'ensayo 7, leon')->count());
    }

    public function test_location_already_in_neighborhood_requires_address_approval_without_overwriting(): void
    {
        $row = $this->planner()->candidate($this->original(['name' => 'Ensayo · Barrio Central', 'neighborhood' => 'Barrio Central']));
        $this->assertSame('manual_review', $row['status']);
        $this->assertSame('location_context_requires_approval', $row['fragments'][0]['kind']);
        $this->assertSame('review_location_before_appending', $row['fragments'][0]['address_action']);
        $this->assertSame([], $row['changes']);
        $this->assertSame('Frente al parque de prueba.', $row['before']['address']);
    }

    public function test_tampered_backup_is_rejected(): void
    {
        $base = $this->original();
        $before = $this->current($base);
        $plan = $this->planner()->preview([$base], ['REV-0001'], [$before]);
        $backup = $this->planner()->backup($plan, ['REV-0001']);
        $backup['records'][0]['changes']['name'] = 'Alterado';
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->receipt($backup, [array_replace($before, ['name' => 'Ensayo 7'])]);
    }

    public function test_changed_geography_requires_a_new_review_before_backup(): void
    {
        $base = $this->original();
        $before = $this->current($base);
        $plan = $this->planner()->preview([$base], ['REV-0001'], [$before]);
        $changed = new CatalogNormalizationPlanner(['León' => ['León']]);
        $this->expectException(InvalidArgumentException::class);
        $changed->backup($plan, ['REV-0001']);
    }

    public function test_partial_audit_preview_never_invents_missing_import_fields_or_authorizes_backup(): void
    {
        $fields = array_flip(['source_reference', 'name', 'department', 'city', 'neighborhood', 'address']);
        $partial = array_intersect_key($this->original(), $fields);
        $plan = $this->planner()->auditPreview([$partial], ['REV-0001']);
        $this->assertSame('pending_original_csv_and_current_snapshot', $plan['records'][0]['status']);
        $this->assertArrayNotHasKey('phone', $plan['records'][0]['before']);
        $this->assertSame(['name' => 'Ensayo 7'], $plan['records'][0]['changes']);
        $this->expectException(InvalidArgumentException::class);
        $this->planner()->backup($plan, ['REV-0001']);
    }
}
