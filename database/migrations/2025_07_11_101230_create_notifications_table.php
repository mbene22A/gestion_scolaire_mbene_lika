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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('message');
            $table->enum('type', ['info', 'bulletin', 'document', 'urgent', 'inscription', 'note'])->default('info');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->boolean('lu')->default(false);
            $table->timestamp('date_lecture')->nullable();
            $table->json('donnees_supplementaires')->nullable();
            $table->string('lien_action')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('envoye_par')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('envoye_par')->references('id')->on('users')->onDelete('set null');
            // index
            $table->index(['user_id', 'lu', 'created_at']);
            $table->index(['type', 'priorite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
