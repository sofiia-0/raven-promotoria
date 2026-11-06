<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_reasons', function (Blueprint $table) {
            // NULL conserva la regla histórica; no se transforma el catálogo existente.
            $table->boolean('requires_explanation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visit_reasons', fn (Blueprint $table) => $table->dropColumn('requires_explanation'));
    }
};
