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
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('matricule_eleve')->unique();
            $table->date('date_naissance');
            $table->text('adresse');
            $table->string('telephone_parent');
            $table->string('email_parent');
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->foreign('classe_id')->references('id')->on('classes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');                 
            //index
            $table->index(['classe_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eleves');
    }
};
