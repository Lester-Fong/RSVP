<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitation_groups', function (Blueprint $table) {
            $table->char('invitation_token_hash', 64)->nullable()->unique();
            $table->text('invitation_token')->nullable();
            $table->unsignedInteger('invitation_link_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('invitation_groups', function (Blueprint $table) {
            $table->dropUnique(['invitation_token_hash']);
            $table->dropColumn(['invitation_token_hash', 'invitation_token', 'invitation_link_version']);
        });
    }
};
