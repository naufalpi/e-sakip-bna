<script setup lang="ts">
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

type SelectValue = string | number;

type SelectOption = {
    id: SelectValue;
    label: string;
    kode?: string | null;
    nama?: string | null;
    singkatan?: string | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: SelectValue[];
        options: SelectOption[];
        placeholder?: string;
        emptyText?: string;
        disabled?: boolean;
    }>(),
    {
        placeholder: 'Pilih OPD',
        emptyText: 'OPD belum tersedia',
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: SelectValue[]];
}>();

const isOpen = ref(false);
const openUpwards = ref(false);
const root = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const searchQuery = ref('');
const dropdownStyle = ref<Record<string, string>>({});

const selectedSet = computed(() => new Set(props.modelValue.map((value) => String(value))));
const selectedOptions = computed(() => props.options.filter((option) => selectedSet.value.has(String(option.id))));
const normalizedSearch = computed(() => searchQuery.value.trim().toLocaleLowerCase('id-ID'));
const filteredOptions = computed(() => {
    if (!normalizedSearch.value) {
        return props.options;
    }

    return props.options.filter((option) =>
        [option.label, option.kode, option.nama, option.singkatan]
            .filter((value) => value !== null && value !== undefined)
            .some((value) => String(value).toLocaleLowerCase('id-ID').includes(normalizedSearch.value)),
    );
});
const summaryLabel = computed(() => {
    if (selectedOptions.value.length === 0) {
        return props.placeholder;
    }

    const names = selectedOptions.value.slice(0, 2).map((option) => option.singkatan || option.nama || option.label);
    const remaining = selectedOptions.value.length - names.length;

    return `${names.join(', ')}${remaining > 0 ? ` +${remaining}` : ''}`;
});

const updateDropdownPosition = (force = false) => {
    if (!force && !isOpen.value) {
        return;
    }

    const rect = root.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    const margin = 12;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const panelMaxHeight = Math.min(380, viewportHeight - margin * 2);
    const panelWidth = Math.min(Math.max(rect.width, 340), viewportWidth - margin * 2);
    const left = Math.min(Math.max(rect.left, margin), viewportWidth - panelWidth - margin);
    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;

    openUpwards.value = spaceBelow < Math.min(300, panelMaxHeight) && spaceAbove > spaceBelow;
    dropdownStyle.value = {
        left: `${left}px`,
        top: openUpwards.value ? 'auto' : `${Math.min(rect.bottom + 8, viewportHeight - panelMaxHeight - margin)}px`,
        bottom: openUpwards.value ? `${Math.min(viewportHeight - rect.top + 8, viewportHeight - margin)}px` : 'auto',
        width: `${panelWidth}px`,
        maxHeight: `${panelMaxHeight}px`,
    };
};

const close = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const toggleOpen = () => {
    if (props.disabled) {
        return;
    }

    if (!isOpen.value) {
        updateDropdownPosition(true);
    }

    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        nextTick(() => {
            updateDropdownPosition(true);
            searchInput.value?.focus();
        });
    } else {
        searchQuery.value = '';
    }
};

const toggleOption = (option: SelectOption) => {
    const current = props.modelValue.map((value) => String(value));
    const optionId = String(option.id);

    if (current.includes(optionId)) {
        emit(
            'update:modelValue',
            props.modelValue.filter((value) => String(value) !== optionId),
        );
        return;
    }

    emit('update:modelValue', [...props.modelValue, option.id]);
};

const clearSelection = () => {
    emit('update:modelValue', []);
};

const selectAll = () => {
    emit(
        'update:modelValue',
        props.options.map((option) => option.id),
    );
};

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target as Node;

    if (!root.value?.contains(target) && !panel.value?.contains(target)) {
        close();
    }
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
    window.addEventListener('resize', updateDropdownPosition);
    window.addEventListener('scroll', updateDropdownPosition, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('resize', updateDropdownPosition);
    window.removeEventListener('scroll', updateDropdownPosition, true);
});
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <button
            type="button"
            class="flex min-h-10 w-full min-w-0 items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-sm shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-[#00336C]/35 hover:bg-blue-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C]/25 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
            :disabled="disabled"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            @click="toggleOpen"
        >
            <span class="min-w-0">
                <span class="block truncate font-semibold" :class="selectedOptions.length ? 'text-slate-950' : 'text-slate-500'">
                    {{ summaryLabel }}
                </span>
                <span v-if="selectedOptions.length" class="mt-0.5 block text-xs font-medium text-slate-500">
                    {{ selectedOptions.length }} OPD dipilih
                </span>
            </span>
            <ChevronDown class="size-4 shrink-0 text-slate-500 transition duration-200" :class="isOpen ? 'rotate-180' : ''" />
        </button>

        <Teleport to="body">
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
                    ref="panel"
                    class="fixed z-[9999] flex min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.14)]"
                    :style="dropdownStyle"
                >
                    <div class="border-b border-slate-100 p-2">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                ref="searchInput"
                                v-model="searchQuery"
                                type="search"
                                class="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-9 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#00336C]/45 focus:bg-white focus:ring-2 focus:ring-[#00336C]/10 [&::-webkit-search-cancel-button]:appearance-none"
                                placeholder="Cari OPD..."
                                aria-label="Cari OPD"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute right-1.5 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-700"
                                aria-label="Hapus pencarian"
                                @click="searchQuery = ''"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 text-xs font-semibold text-[#00336C] hover:bg-blue-50"
                                @click="selectAll"
                            >
                                Pilih semua
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100"
                                @click="clearSelection"
                            >
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2" role="listbox">
                        <div v-if="options.length === 0" class="px-3 py-3 text-sm font-medium text-slate-500">
                            {{ emptyText }}
                        </div>
                        <div v-else-if="filteredOptions.length === 0" class="px-3 py-6 text-center text-sm font-medium text-slate-500">
                            OPD tidak ditemukan.
                        </div>
                        <button
                            v-for="option in filteredOptions"
                            v-else
                            :key="String(option.id)"
                            type="button"
                            class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2.5 text-left transition"
                            :class="
                                selectedSet.has(String(option.id))
                                    ? 'bg-[#00336C] text-white shadow-sm'
                                    : 'text-slate-700 hover:bg-blue-50 hover:text-slate-950'
                            "
                            role="option"
                            :aria-selected="selectedSet.has(String(option.id))"
                            @click="toggleOption(option)"
                        >
                            <span class="min-w-0">
                                <span class="block break-words text-sm font-semibold leading-5">
                                    {{ option.singkatan || option.nama || option.label }}
                                </span>
                                <span
                                    class="mt-0.5 block break-words text-xs font-medium leading-4"
                                    :class="selectedSet.has(String(option.id)) ? 'text-blue-100' : 'text-slate-500'"
                                >
                                    {{ option.kode ? `${option.kode} - ${option.nama || option.label}` : option.label }}
                                </span>
                            </span>
                            <Check v-if="selectedSet.has(String(option.id))" class="mt-0.5 size-4 shrink-0 text-white" />
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
