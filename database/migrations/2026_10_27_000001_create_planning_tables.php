<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promoter_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->enum('status', ['BORRADOR', 'ENVIADO'])->default('BORRADOR');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // Un cronograma por promotora y mes.
            $table->unique(['promoter_id', 'year', 'month'], 'uq_monthly_schedules_period');
        });

        Schema::create('planned_weeks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monthly_schedule_id')->constrained('monthly_schedules')->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['monthly_schedule_id', 'week_number'], 'uq_planned_weeks_number');
        });

        Schema::create('planned_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planned_week_id')->constrained('planned_weeks')->cascadeOnDelete();
            $table->enum('activity_type', ['VISITA', 'REUNION', 'FERIADO', 'OTRA']);
            $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->restrictOnDelete();
            $table->date('scheduled_date');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // La misma farmacia no se planifica dos veces el mismo día (NULL no colisiona).
            $table->unique(['planned_week_id', 'scheduled_date', 'pharmacy_id'], 'uq_planned_activities_visit');
            $table->index(['planned_week_id', 'scheduled_date'], 'ix_planned_activities_week_date');
        });

        // CHECK solo en MySQL (≥ 8.0.16): la suite local en SQLite no los admite vía ALTER.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE monthly_schedules
                ADD CONSTRAINT ck_monthly_schedules_month CHECK (month BETWEEN 1 AND 12),
                ADD CONSTRAINT ck_monthly_schedules_submitted CHECK (
                    (status = \'BORRADOR\' AND submitted_at IS NULL)
                    OR (status = \'ENVIADO\' AND submitted_at IS NOT NULL)
                )');
            DB::statement('ALTER TABLE planned_weeks
                ADD CONSTRAINT ck_planned_weeks_number CHECK (week_number BETWEEN 1 AND 5),
                ADD CONSTRAINT ck_planned_weeks_range CHECK (ends_on >= starts_on)');
            DB::statement('ALTER TABLE planned_activities
                ADD CONSTRAINT ck_planned_activities_visit CHECK (
                    activity_type <> \'VISITA\' OR pharmacy_id IS NOT NULL
                )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_activities');
        Schema::dropIfExists('planned_weeks');
        Schema::dropIfExists('monthly_schedules');
    }
};
