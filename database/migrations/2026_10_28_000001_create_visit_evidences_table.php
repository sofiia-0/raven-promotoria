<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_evidences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->uuid('batch_key');
            $table->string('status', 16)->default('uploading');
            $table->string('disk', 80);
            $table->string('path', 255)->unique();
            $table->string('original_name', 255);
            $table->string('extension', 10);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('attached_at')->nullable();
            $table->timestamps();
            $table->index(['uploaded_by', 'batch_key', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_evidences');
    }
};
