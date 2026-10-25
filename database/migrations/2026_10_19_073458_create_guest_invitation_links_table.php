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
        Schema::create('guest_invitation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->unique()->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->text('token')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_invitation_links');
    }
};
