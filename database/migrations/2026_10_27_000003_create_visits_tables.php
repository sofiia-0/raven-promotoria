<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de motivos: visita no realizada y reprogramación.
        Schema::create('visit_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique('uq_visit_reasons_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160)->unique('uq_checklist_items_name');
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Cada reprogramación abre un nuevo «intento» de la actividad.
        Schema::table('planned_activities', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reschedule_count')->default(0)->after('notes');
        });

        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique('uq_visits_idempotency_key');
            $table->foreignId('promoter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->restrictOnDelete();
            // NULL = visita fuera de plan.
            $table->foreignId('planned_activity_id')->nullable()->constrained('planned_activities')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt')->nullable();
            $table->enum('status', ['REALIZADA', 'NO_REALIZADA']);
            $table->foreignId('visit_reason_id')->nullable()->constrained('visit_reasons')->restrictOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();

            // Un solo registro por intento de una actividad planificada (NULL no colisiona).
            $table->unique(['planned_activity_id', 'attempt'], 'uq_visits_activity_attempt');
            $table->index(['promoter_id', 'visited_at'], 'ix_visits_promoter_visited');
            $table->index(['pharmacy_id', 'visited_at'], 'ix_visits_pharmacy_visited');
        });

        Schema::create('visit_checklist', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('checklist_items')->restrictOnDelete();
            $table->boolean('checked');

            $table->unique(['visit_id', 'checklist_item_id'], 'uq_visit_checklist_item');
        });

        Schema::create('activity_reschedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planned_activity_id')->constrained('planned_activities')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->foreignId('visit_reason_id')->constrained('visit_reasons')->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['planned_activity_id', 'created_at'], 'ix_activity_reschedules_activity');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE visits
                ADD CONSTRAINT ck_visits_reason CHECK (
                    (status = \'REALIZADA\' AND visit_reason_id IS NULL)
                    OR (status = \'NO_REALIZADA\' AND visit_reason_id IS NOT NULL)
                ),
                ADD CONSTRAINT ck_visits_plan CHECK ((planned_activity_id IS NULL) = (attempt IS NULL)),
                ADD CONSTRAINT ck_visits_location CHECK (
                    (latitude IS NULL AND longitude IS NULL)
                    OR (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
                )');
            DB::statement('ALTER TABLE activity_reschedules
                ADD CONSTRAINT ck_activity_reschedules_dates CHECK (to_date <> from_date)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_reschedules');
        Schema::dropIfExists('visit_checklist');
        Schema::dropIfExists('visits');
        Schema::table('planned_activities', function (Blueprint $table): void {
            $table->dropColumn('reschedule_count');
        });
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('visit_reasons');
    }
};
