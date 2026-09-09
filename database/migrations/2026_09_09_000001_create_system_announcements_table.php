<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('message');
            $table->string('type', 20)->default('info');
            $table->string('audience', 20)->default('all');
            $table->json('target_roles')->nullable();
            $table->json('target_opd_ids')->nullable();
            $table->string('link_label', 80)->nullable();
            $table->text('link_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dismissible')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'system_announcements_schedule_index');
            $table->index(['audience', 'type'], 'system_announcements_audience_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_announcements');
    }
};
