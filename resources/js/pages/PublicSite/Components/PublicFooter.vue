<script setup lang="ts">
defineProps<{
    year: number;
}>();
</script>

<template>
    <footer class="bg-[#00336C] py-8 text-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-sm text-blue-100 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-semibold text-white">E-SAKIP Kabupaten Banjarnegara</p>
                <p>Data publik diperbarui dari status dokumen resmi yang sudah diverifikasi.</p>
            </div>
            <div class="border-t border-white/10 pt-3 text-xs text-blue-200">
                &copy; {{ year }} Dinas Komunikasi dan Informatika Kabupaten Banjarnegara.
            </div>
        </div>
    </footer>
</template>
