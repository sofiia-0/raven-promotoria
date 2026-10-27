<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promoter_id')->constrained('users')->restrictOnDelete();
            // En un alta todavía NO existe un establecimiento definitivo.
            $table->foreignId('pharmacy_id')->nullable()->constrained('pharmacies')->restrictOnDelete();
            $table->string('type', 16); // alta | baja
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->json('data')->nullable(); // Propuesta de alta, no afecta el catálogo.
            $table->text('reason');
            // Campos preparados para resolución por Coordinadora en la siguiente etapa.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['promoter_id', 'created_at']);
            $table->index(['status', 'type']);
            $table->index(['pharmacy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_requests');
    }
};
