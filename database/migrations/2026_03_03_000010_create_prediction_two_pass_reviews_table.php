<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_two_pass_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('baseline_label', 20);
            $table->decimal('baseline_confidence', 5, 2)->nullable();
            $table->string('assisted_label', 20);
            $table->decimal('assisted_confidence', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['prediction_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_two_pass_reviews');
    }
};
