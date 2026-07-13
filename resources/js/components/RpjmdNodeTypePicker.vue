<script setup lang="ts">
import { Check, ChevronDown, Layers3 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Option = {
    value: string;
    label: string;
};

const props = defineProps<{
    id?: string;
    modelValue: string;
    options: Option[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const root = ref<HTMLElement | null>(null);

const descriptions: Record<string, string> = {
    visi: 'Rumusan utama RPJMD',
    misi: 'Arah pencapaian visi',
    tujuan: 'Hasil utama daerah',
    indikator_tujuan: 'Ukuran keberhasilan tujuan',
    target_tujuan: 'Target tahunan tujuan',
    sasaran: 'Sasaran strategis daerah',
    indikator_sasaran: 'Ukuran pencapaian sasaran',
    target_sasaran: 'Target tahunan sasaran',
    strategi: 'Cara mencapai sasaran',
    program: 'Program prioritas RPJMD',
    indikator_program: 'Ukuran hasil program',
    target_program: 'Target tahunan program',
    program_opd: 'PD penanggung jawab program',
};

const groups = [
    { label: 'Arah RPJMD', values: ['visi', 'misi', 'tujuan'] },
    { label: 'Pengukuran Tujuan', values: ['indikator_tujuan', 'target_tujuan'] },
    { label: 'Sasaran & Strategi', values: ['sasaran', 'indikator_sasaran', 'target_sasaran', 'strategi'] },
    { label: 'Program', values: ['program', 'indikator_program', 'target_program', 'program_opd'] },
];

const selectedOption = computed(() => props.options.find((option) => option.value === props.modelValue) ?? props.options[0]);
const groupedOptions = computed(() =>
    groups
        .map((group) => ({
            ...group,
            options: group.values
                .map((value) => props.options.find((option) => option.value === value))
                .filter((option): option is Option => Boolean(option)),
        }))
        .filter((group) => group.options.length > 0),
);

const selectOption = (value: string) => {
    emit('update:modelValue', value);
    isOpen.value = false;
};

const handleDocumentClick = (event: MouseEvent) => {
    if (!root.value?.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            :id="id"
            type="button"
            class="group flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-[#00336C]/35 hover:bg-blue-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C]/25"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            @click="isOpen = !isOpen"
        >
            <span class="flex min-w-0 items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[#00336C] transition group-hover:bg-white">
                    <Layers3 class="size-4" />
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-slate-950">{{ selectedOption?.label }}</span>
                    <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">
                        {{ descriptions[selectedOption?.value ?? ''] ?? 'Data cascading RPJMD' }}
                    </span>
                </span>
            </span>
            <ChevronDown class="size-4 shrink-0 text-slate-500 transition duration-200" :class="isOpen ? 'rotate-180' : ''" />
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute left-0 right-0 z-50 mt-2 max-h-[420px] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-[0_18px_45px_rgba(15,23,42,0.14)]"
                role="listbox"
            >
                <div v-for="group in groupedOptions" :key="group.label" class="mt-2 first:mt-0">
                    <div class="px-2 pb-1 pt-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        {{ group.label }}
                    </div>
                    <button
                        v-for="option in group.options"
                        :key="option.value"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left transition"
                        :class="
                            option.value === modelValue
                                ? 'bg-[#00336C] text-white shadow-sm'
                                : 'text-slate-700 hover:bg-blue-50 hover:text-slate-950'
                        "
                        role="option"
                        :aria-selected="option.value === modelValue"
                        @click="selectOption(option.value)"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">{{ option.label }}</span>
                            <span
                                class="mt-0.5 block text-xs font-medium"
                                :class="option.value === modelValue ? 'text-blue-100' : 'text-slate-500'"
                            >
                                {{ descriptions[option.value] ?? 'Data cascading RPJMD' }}
                            </span>
                        </span>
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full"
                            :class="option.value === modelValue ? 'bg-white/15 text-white' : 'text-transparent'"
                        >
                            <Check class="size-3.5" />
                        </span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
