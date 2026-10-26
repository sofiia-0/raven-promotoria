<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table): void {
            // Referencia estable del catálogo maestro (REV-xxxx); nunca depende del nombre.
            $table->string('source_reference', 30)->nullable()->unique();
            $table->unsignedSmallInteger('dependents')->nullable();
            $table->unsignedSmallInteger('shifts')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('email')->nullable();
            // Responsable histórico del Excel; NO autoriza acceso ni crea asignaciones.
            $table->string('source_responsible', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table): void {
            $table->dropUnique(['source_reference']);
            $table->dropColumn([
                'source_reference', 'dependents', 'shifts', 'phone', 'email', 'source_responsible',
            ]);
        });
    }
};
