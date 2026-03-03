<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->string('finding_location')->nullable()->after('heatmap_path');
            $table->decimal('severity_score', 5, 2)->nullable()->after('finding_location');
            $table->string('confidence_band')->nullable()->after('severity_score');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['finding_location', 'severity_score', 'confidence_band']);
        });
    }
};
