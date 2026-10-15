<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('account_members', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('owner');
            $table->timestamps();

            $table->primary(['account_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('partner_one_name');
            $table->string('partner_two_name');
            $table->date('wedding_date')->nullable();
            $table->time('wedding_time')->nullable();
            $table->string('ceremony_venue', 500)->nullable();
            $table->string('reception_venue', 500)->nullable();
            $table->string('public_slug')->unique();
            $table->string('status', 32)->default('planning');
            $table->timestamps();

            $table->index(['account_id', 'wedding_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weddings');
        Schema::dropIfExists('account_members');
        Schema::dropIfExists('accounts');
    }
};
