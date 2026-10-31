<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Al validar o rechazar se exige revisor y fecha; al rechazar, además, el motivo (RF-AB).
     * CHECK solo en MySQL (≥ 8.0.16): la suite en SQLite no los admite vía ALTER.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE pharmacy_requests
            ADD CONSTRAINT ck_pharmacy_requests_type CHECK (type IN ('alta', 'baja')),
            ADD CONSTRAINT ck_pharmacy_requests_status CHECK (status IN ('pending', 'approved', 'rejected')),
            ADD CONSTRAINT ck_pharmacy_requests_review CHECK (
                (status = 'pending' AND reviewed_by IS NULL AND reviewed_at IS NULL)
                OR (status IN ('approved', 'rejected') AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL)
            ),
            ADD CONSTRAINT ck_pharmacy_requests_rejection_note CHECK (status <> 'rejected' OR review_note IS NOT NULL),
            ADD CONSTRAINT ck_pharmacy_requests_baja_pharmacy CHECK (type <> 'baja' OR pharmacy_id IS NOT NULL)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['type', 'status', 'review', 'rejection_note', 'baja_pharmacy'] as $name) {
            DB::statement("ALTER TABLE pharmacy_requests DROP CHECK ck_pharmacy_requests_{$name}");
        }
    }
};
