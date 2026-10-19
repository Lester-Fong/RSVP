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
        Schema::table('invitation_groups', function (Blueprint $table) {
            $table->unsignedInteger('rsvp_version')->default(0);
        });
        Schema::create('rsvp_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('status', ['attending', 'declined']);
            $table->timestamp('responded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rsvp_responses');
        Schema::table('invitation_groups', function (Blueprint $table) {
            $table->dropColumn('rsvp_version');
        });
    }
};
