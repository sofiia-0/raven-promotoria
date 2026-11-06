<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_assignments', function (Blueprint $table): void {
            $table->string('assignment_kind', 20)->default('responsible');
            $table->date('coverage_starts_on')->nullable();
            $table->date('coverage_ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('replacement_assignment_id')->nullable()->constrained('pharmacy_assignments')->restrictOnDelete();
            $table->index(['pharmacy_id', 'promoter_id', 'assignment_kind'], 'ix_assignment_relationship');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacy_assignments', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['ended_by']);
            $table->dropForeign(['replacement_assignment_id']);
            $table->dropIndex('ix_assignment_relationship');
            $table->dropColumn(['assignment_kind', 'coverage_starts_on', 'coverage_ends_on', 'created_by', 'ended_by', 'replacement_assignment_id']);
        });
    }
};
