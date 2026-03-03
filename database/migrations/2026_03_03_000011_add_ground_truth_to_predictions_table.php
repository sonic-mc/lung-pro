<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->string('ground_truth_label')->nullable()->after('predicted_label');
            $table->string('ground_truth_source')->nullable()->after('ground_truth_label');
            $table->timestamp('ground_truth_recorded_at')->nullable()->after('ground_truth_source');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['ground_truth_label', 'ground_truth_source', 'ground_truth_recorded_at']);
        });
    }
};
