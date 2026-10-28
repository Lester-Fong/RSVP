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
        Schema::create('rsvp_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependent_guest_id')->unique()->constrained('guests')->restrictOnDelete();
            $table->foreignId('representative_guest_id')->nullable()->index()->constrained('guests')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rsvp_delegations');
    }
};
