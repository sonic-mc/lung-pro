<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->decimal('tumor_area_mm2', 10, 2)->nullable()->after('nodule_area_px');
            $table->decimal('tumor_volume_mm3', 12, 2)->nullable()->after('tumor_area_mm2');
            $table->decimal('growth_rate_percent', 8, 2)->nullable()->after('tumor_volume_mm3');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['tumor_area_mm2', 'tumor_volume_mm3', 'growth_rate_percent']);
        });
    }
};
