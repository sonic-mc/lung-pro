<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->decimal('nodule_diameter_mm', 6, 2)->nullable()->after('confidence_band');
            $table->decimal('nodule_area_px', 10, 2)->nullable()->after('nodule_diameter_mm');
            $table->decimal('nodule_burden_percent', 6, 2)->nullable()->after('nodule_area_px');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['nodule_diameter_mm', 'nodule_area_px', 'nodule_burden_percent']);
        });
    }
};
