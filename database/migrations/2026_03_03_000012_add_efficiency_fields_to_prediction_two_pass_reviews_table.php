<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prediction_two_pass_reviews', function (Blueprint $table) {
            $table->unsignedInteger('baseline_time_seconds')->nullable()->after('baseline_confidence');
            $table->unsignedInteger('assisted_time_seconds')->nullable()->after('assisted_confidence');
            $table->boolean('overlooked_nodule_recovered')->default(false)->after('assisted_time_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('prediction_two_pass_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'baseline_time_seconds',
                'assisted_time_seconds',
                'overlooked_nodule_recovered',
            ]);
        });
    }
};
