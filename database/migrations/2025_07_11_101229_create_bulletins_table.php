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
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->enum('periode', ['trimestre_1', 'trimestre_2', 'trimestre_3']);
            $table->string('annee_scolaire');
            $table->decimal('moyenne_generale', 4, 2);
            $table->enum('mention', ['Excellent', 'Très bien', 'Bien', 'Assez bien', 'Passable', 'Insuffisant']);
            $table->integer('rang');
            $table->integer('total_eleves');
            $table->string('chemin_pdf')->nullable();
            $table->boolean('publie')->default(false);
            $table->text('appreciation')->nullable();
            $table->unsignedBigInteger('eleve_id');
            $table->timestamps();
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['eleve_id', 'periode', 'annee_scolaire']);
            
            // index
            $table->index(['eleve_id', 'periode', 'annee_scolaire']);
            $table->index(['publie', 'periode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulletins');
    }
};
