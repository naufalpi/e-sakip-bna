<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_rpjmd_program_pemerintahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_rpjmd_id')->constrained('program_rpjmd')->cascadeOnDelete();
            $table->foreignId('program_pemerintahan_id')->constrained('program_pemerintahan')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['program_rpjmd_id', 'program_pemerintahan_id'], 'program_rpjmd_program_ref_unique');
            $table->index('program_pemerintahan_id', 'program_rpjmd_program_ref_program_index');
        });

        if (Schema::hasColumn('program_rpjmd', 'program_pemerintahan_id')) {
            $programReferences = DB::table('program_pemerintahan')
                ->whereNull('deleted_at')
                ->get(['id', 'nama']);
            $programReferencesById = $programReferences->keyBy('id');
            $programReferencesByName = $programReferences->groupBy(fn ($program) => $this->normalizeProgramName($program->nama));

            DB::table('program_rpjmd')
                ->whereNotNull('program_pemerintahan_id')
                ->orderBy('id')
                ->select(['id', 'program_pemerintahan_id'])
                ->chunkById(200, function ($programs) use ($programReferencesById, $programReferencesByName): void {
                    $now = now();

                    foreach ($programs as $program) {
                        $programReference = $programReferencesById->get($program->program_pemerintahan_id);
                        $referenceIds = $programReference
                            ? $programReferencesByName
                                ->get($this->normalizeProgramName($programReference->nama), collect())
                                ->pluck('id')
                                ->all()
                            : [$program->program_pemerintahan_id];

                        foreach ($referenceIds as $referenceId) {
                            DB::table('program_rpjmd_program_pemerintahan')->updateOrInsert(
                                [
                                    'program_rpjmd_id' => $program->id,
                                    'program_pemerintahan_id' => $referenceId,
                                ],
                                [
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ],
                            );
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('program_rpjmd_program_pemerintahan');
    }

    private function normalizeProgramName(?string $name): string
    {
        return strtolower((string) preg_replace('/\s+/', ' ', trim((string) $name)));
    }
};
