<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bitácora: solo inserción (sin updated_at, sin borrado lógico). */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('auditable_type', 60);
            $table->unsignedBigInteger('auditable_id');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'ix_audit_logs_auditable');
            $table->index(['actor_id', 'created_at'], 'ix_audit_logs_actor_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
