<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_type', 80)->nullable();
            $table->string('category', 20)->nullable();
            $table->string('neighborhood')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('department', 120)->nullable();
            // Valor numérico del campo FRECUENCIA del fichero original.
            // Su unidad/regla de negocio se definirá al validar los datos.
            $table->unsignedTinyInteger('frequency')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department', 'city']);
            $table->index(['client_type', 'category']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
