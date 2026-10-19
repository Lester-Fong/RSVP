<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('contact_email', 254)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->timestamps();
            $table->index(['wedding_id', 'id']);
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->index(['invitation_group_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
        Schema::dropIfExists('invitation_groups');
    }
};
