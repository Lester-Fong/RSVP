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
        Schema::create('website_revision_media', function (Blueprint $table) {
            $table->foreignId('website_revision_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('wedding_media_asset_id')->index()->constrained()->restrictOnDelete();
            $table->primary(['website_revision_id', 'wedding_media_asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_revision_media');
    }
};
