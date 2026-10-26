<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')
                ->constrained('pharmacies')
                ->restrictOnDelete();
            $table->foreignId('promoter_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['pharmacy_id', 'ended_at']);
            $table->index(['promoter_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_assignments');
    }
};
