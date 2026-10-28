<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wedding_media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('wedding_id')->index()->constrained()->restrictOnDelete();
            $table->enum('kind', ['image']);
            $table->string('disk', 64);
            $table->string('path');
            $table->string('mime_type', 64);
            $table->unsignedInteger('byte_size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->timestamps();
            $table->unique(['disk', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wedding_media_assets');
    }
};
