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
        Schema::create('website_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->unsignedSmallInteger('schema_version');
            $table->json('wedding_facts');
            $table->json('content');
            $table->string('hero_photo_disk', 64)->nullable();
            $table->string('hero_photo_path')->nullable();
            $table->char('fingerprint', 64);
            $table->timestamp('published_at');
            $table->unique(['wedding_id', 'revision_number']);
            $table->index(['hero_photo_disk', 'hero_photo_path'], 'website_revisions_photo_reference_index');
        });
        Schema::table('weddings', function (Blueprint $table) {
            $table->foreignId('published_revision_id')->nullable()->constrained('website_revisions')->restrictOnDelete();
            $table->unsignedInteger('publication_version')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weddings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_revision_id');
            $table->dropColumn('publication_version');
        });
        Schema::dropIfExists('website_revisions');
    }
};
