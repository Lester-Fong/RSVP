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
        foreach (['website_drafts', 'website_revisions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('template_key', 64)->default('modern-classic');
                $table->unsignedSmallInteger('template_version')->default(1);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['website_revisions', 'website_drafts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['template_key', 'template_version']);
            });
        }
    }
};
