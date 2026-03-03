<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->string('cancer_stage')->nullable()->after('region_confidence_score');
            $table->text('confidence_reasoning')->nullable()->after('cancer_stage');
            $table->json('ct_viewer')->nullable()->after('confidence_reasoning');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['cancer_stage', 'confidence_reasoning', 'ct_viewer']);
        });
    }
};
