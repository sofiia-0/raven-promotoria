<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RF-AB: integridad de la revisión en la base de datos. Solo MySQL ejecuta los CHECK;
 * en SQLite :memory: se omite (correr con DB_CONNECTION=mysql y una BD de pruebas desechable).
 */
class CoordinatorRequestConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Los CHECK se ejercitan solo en MySQL.');
        }
    }

    public function test_review_fields_are_enforced_by_check_constraints(): void
    {
        $promoter = User::factory()->promoter()->create();
        $coordinator = User::factory()->coordinator()->create();
        $base = ['promoter_id' => $promoter->id, 'type' => 'alta', 'data' => ['name' => 'X'], 'reason' => 'Motivo'];

        $invalid = [
            'aprobada sin revisor' => ['status' => 'approved', 'reviewed_at' => now()],
            'pendiente con revisor' => ['status' => 'pending', 'reviewed_by' => $coordinator->id, 'reviewed_at' => now()],
            'rechazada sin motivo' => ['status' => 'rejected', 'reviewed_by' => $coordinator->id, 'reviewed_at' => now()],
            'estado desconocido' => ['status' => 'validada'],
            'baja sin farmacia' => ['status' => 'pending', 'type' => 'baja'],
        ];

        foreach ($invalid as $case => $attributes) {
            try {
                PharmacyRequest::query()->create($attributes + $base);
                $this->fail("La BD debió rechazar: {$case}");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        PharmacyRequest::query()->create(['status' => 'rejected', 'reviewed_by' => $coordinator->id, 'reviewed_at' => now(), 'review_note' => 'Duplicada'] + $base);
        PharmacyRequest::query()->create(['status' => 'pending', 'type' => 'baja', 'pharmacy_id' => Pharmacy::factory()->create()->id] + $base);
        $this->assertSame(2, PharmacyRequest::count());
    }
}
