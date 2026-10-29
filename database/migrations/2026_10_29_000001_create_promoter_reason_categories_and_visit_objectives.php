<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoter_reason_categories', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 30);
            $table->string('code', 60);
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_provisional')->default(true);
            $table->boolean('is_other')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['domain', 'code']);
            $table->index(['domain', 'is_active', 'sort_order']);
        });
        Schema::create('visit_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained('visits')->restrictOnDelete();
            $table->foreignId('promoter_reason_category_id')->constrained('promoter_reason_categories')->restrictOnDelete();
            $table->string('category_code', 60);
            $table->string('category_label', 150);
            $table->boolean('is_provisional')->default(true);
            $table->text('explanation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_objectives');
        Schema::dropIfExists('promoter_reason_categories');
    }
};
