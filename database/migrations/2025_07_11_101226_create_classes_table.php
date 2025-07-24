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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('niveau', 50);
            $table->string('annee_scolaire', 50);
            $table->unsignedBigInteger('enseignant_principal_id')->nullable();
            $table->timestamps();
            
            // Index et clé étrangère
            $table->foreign('enseignant_principal_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['niveau', 'annee_scolaire']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
