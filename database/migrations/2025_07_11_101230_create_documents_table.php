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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('nom_fichier');
            $table->string('nom_original');
            $table->string('chemin_fichier');
            $table->string('type_fichier');
            $table->string('mime_type');
            $table->unsignedBigInteger('taille_fichier');
            $table->enum('type_document', ['certificat_scolarite', 'bulletin', 'justificatif', 'autre'])->default('autre');
            $table->boolean('obligatoire')->default(false);
            $table->boolean('valide')->default(false);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            // index
            $table->index(['eleve_id', 'type_document']);
            $table->index(['obligatoire', 'valide']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
