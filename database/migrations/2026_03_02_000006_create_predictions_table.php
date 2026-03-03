<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('predicted_label');
            $table->decimal('probability', 5, 4);
            $table->string('heatmap_path')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('model_version')->default('hybrid-v1');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique('scan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
