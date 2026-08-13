<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCancellationColumns('rpjmd');
        $this->addCancellationColumns('renstra_opd');
    }

    public function down(): void
    {
        $this->dropCancellationColumns('renstra_opd');
        $this->dropCancellationColumns('rpjmd');
    }

    private function addCancellationColumns(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'dibatalkan_oleh')) {
                $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn($tableName, 'dibatalkan_pada')) {
                $table->timestamp('dibatalkan_pada')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'alasan_pembatalan')) {
                $table->text('alasan_pembatalan')->nullable();
            }
        });
    }

    private function dropCancellationColumns(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'dibatalkan_oleh')) {
                $table->dropConstrainedForeignId('dibatalkan_oleh');
            }

            if (Schema::hasColumn($tableName, 'dibatalkan_pada')) {
                $table->dropColumn('dibatalkan_pada');
            }

            if (Schema::hasColumn($tableName, 'alasan_pembatalan')) {
                $table->dropColumn('alasan_pembatalan');
            }
        });
    }
};
