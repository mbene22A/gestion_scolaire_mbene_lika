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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->decimal('valeur', 4, 2);
            $table->enum('periode', ['trimestre_1', 'trimestre_2', 'trimestre_3']);
            $table->date('date_note');
            $table->string('type_evaluation')->default('devoir');
            $table->text('commentaire')->nullable();
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('matiere_id');
            $table->unsignedBigInteger('classe_id');
            $table->timestamps();
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('matiere_id')->references('id')->on('matieres')->onDelete('cascade');
            $table->foreign('classe_id')->references('id')->on('classes')->onDelete('cascade');
                        
            // index
            $table->index(['eleve_id', 'matiere_id', 'periode']);
            $table->index(['classe_id', 'periode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
