<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->decimal('region_confidence_score', 5, 2)->nullable()->after('confidence_band');
            $table->json('explanation_maps')->nullable()->after('region_confidence_score');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['region_confidence_score', 'explanation_maps']);
        });
    }
};
