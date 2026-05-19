<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('module')->index();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('urusan_pemerintahan', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('opds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urusan_pemerintahan_id')->nullable()->constrained('urusan_pemerintahan')->nullOnDelete();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('singkatan')->nullable();
            $table->string('jenis')->nullable()->index();
            $table->text('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();
            $table->string('nama_kepala')->nullable();
            $table->string('nip_kepala', 30)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'nama']);
        });

        Schema::create('opd_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained('opds')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('opd_units')->nullOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->string('jenis_unit')->nullable()->index();
            $table->string('nama_pimpinan')->nullable();
            $table->string('nip_pimpinan', 30)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['opd_id', 'kode']);
            $table->index(['opd_id', 'status']);
        });

        Schema::create('periode_tahun', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->string('nama');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('satuan_indikator', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('simbol', 30)->nullable();
            $table->string('jenis')->nullable()->index();
            $table->text('deskripsi')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('umum')->index();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('type', 30)->default('string');
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('opd_id')->nullable()->after('id')->constrained('opds')->nullOnDelete();
            $table->string('status', 20)->default('active')->after('password')->index();
            $table->softDeletes();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['role_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['permission_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50)->index();
            $table->string('model_type')->nullable()->index();
            $table->unsignedBigInteger('model_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opd_id');
            $table->dropColumn(['status', 'deleted_at']);
        });

        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('satuan_indikator');
        Schema::dropIfExists('periode_tahun');
        Schema::dropIfExists('opd_units');
        Schema::dropIfExists('opds');
        Schema::dropIfExists('urusan_pemerintahan');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
