<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_catalog_options', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 30);
            $table->string('name');
            $table->string('normalized_name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['domain', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_catalog_options');
    }
};
