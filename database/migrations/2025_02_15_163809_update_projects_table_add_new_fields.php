<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('api_documentation')->nullable()->after('ai_descriptions');
            $table->text('overall_ai_description')->nullable()->after('api_documentation');
            $table->json('validation_rules')->nullable()->after('overall_ai_description');
            $table->json('error_responses')->nullable()->after('validation_rules');
            $table->string('status')->default('draft')->after('error_responses');
            $table->timestamp('last_generated_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'api_documentation',
                'overall_ai_description',
                'validation_rules',
                'error_responses',
                'status',
                'last_generated_at',
            ]);
        });
    }
};
