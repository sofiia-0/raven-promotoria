<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('before');
            $table->json('after');
            $table->timestamps();
            $table->index(['pharmacy_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_changes');
    }
};
