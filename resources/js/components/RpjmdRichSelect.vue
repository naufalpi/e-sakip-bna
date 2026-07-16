<script setup lang="ts">
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

type SelectValue = string | number | boolean | null | undefined;

type SelectOption = {
    id?: string | number;
    value?: SelectValue;
    label: string;
    description?: string | null;
    badge?: string | number | null;
    group?: string | null;
    disabled?: boolean;
};

const props = withDefaults(
    defineProps<{
        id?: string;
        modelValue?: SelectValue;
        options: SelectOption[];
        placeholder?: string;
        emptyText?: string;
        invalid?: boolean;
        disabled?: boolean;
        placement?: 'auto' | 'bottom' | 'top';
    }>(),
    {
        placeholder: 'Pilih data',
        emptyText: 'Data belum tersedia',
        invalid: false,
        disabled: false,
        placement: 'auto',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: SelectValue];
}>();

const isOpen = ref(false);
const openUpwards = ref(false);
const root = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const searchQuery = ref('');
const dropdownStyle = ref<Record<string, string>>({});

const optionValue = (option: SelectOption): SelectValue => option.value ?? option.id ?? '';
const sameValue = (left: SelectValue, right: SelectValue) => String(left ?? '') === String(right ?? '');
const selectedOption = computed(() => props.options.find((option) => sameValue(optionValue(option), props.modelValue)));
const normalizedSearch = computed(() => searchQuery.value.trim().toLocaleLowerCase('id-ID'));
const filteredOptions = computed(() => {
    if (!normalizedSearch.value) {
        return props.options;
    }

    return props.options.filter((option) =>
        [option.label, option.description, option.group, option.badge]
            .filter((value) => value !== null && value !== undefined)
            .some((value) => String(value).toLocaleLowerCase('id-ID').includes(normalizedSearch.value)),
    );
});
const groupedOptions = computed(() => {
    const groups = new Map<string, SelectOption[]>();

    filteredOptions.value.forEach((option) => {
        const group = option.group?.trim() || '';
        const items = groups.get(group) ?? [];
        items.push(option);
        groups.set(group, items);
    });

    return Array.from(groups, ([label, options]) => ({ label, options }));
});
const close = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

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
    const panelMaxHeight = Math.min(420, viewportHeight - margin * 2);
    const panelWidth = Math.min(Math.max(rect.width, 320), viewportWidth - margin * 2);
    const left = Math.min(Math.max(rect.left, margin), viewportWidth - panelWidth - margin);
    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;

    if (props.placement === 'bottom') {
        openUpwards.value = false;
    } else if (props.placement === 'top') {
        openUpwards.value = true;
    } else {
        openUpwards.value = spaceBelow < Math.min(320, panelMaxHeight) && spaceAbove > spaceBelow;
    }

    dropdownStyle.value = {
        left: `${left}px`,
        top: openUpwards.value ? 'auto' : `${Math.min(rect.bottom + 8, viewportHeight - panelMaxHeight - margin)}px`,
        bottom: openUpwards.value ? `${Math.min(viewportHeight - rect.top + 8, viewportHeight - margin)}px` : 'auto',
        width: `${panelWidth}px`,
        maxHeight: `${panelMaxHeight}px`,
    };
};

const selectOption = (option: SelectOption) => {
    if (option.disabled) {
        return;
    }

    emit('update:modelValue', optionValue(option));
    close();
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
            :id="id"
            type="button"
            class="group flex min-h-11 w-full min-w-0 items-center justify-between gap-3 overflow-hidden rounded-xl border bg-white px-3.5 py-2.5 text-left text-sm shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-[#00336C]/35 hover:bg-blue-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#00336C]/25 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
            :class="invalid ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200'"
            :disabled="disabled"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            @click="toggleOpen"
        >
            <span class="min-w-0">
                <span class="block truncate font-semibold" :class="selectedOption ? 'text-slate-950' : 'text-slate-500'">
                    {{ selectedOption?.label ?? placeholder }}
                </span>
                <span v-if="selectedOption?.description" class="mt-0.5 block truncate text-xs font-medium text-slate-500">
                    {{ selectedOption.description }}
                </span>
            </span>
            <span class="flex shrink-0 items-center gap-2">
                <span
                    v-if="selectedOption?.badge"
                    class="hidden rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-[#00336C] sm:inline-flex"
                >
                    {{ selectedOption.badge }}
                </span>
                <ChevronDown class="size-4 text-slate-500 transition duration-200" :class="isOpen ? 'rotate-180' : ''" />
            </span>
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
                <div v-if="options.length" class="border-b border-slate-100 p-2">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            ref="searchInput"
                            v-model="searchQuery"
                            type="search"
                            class="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-9 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#00336C]/45 focus:bg-white focus:ring-2 focus:ring-[#00336C]/10 [&::-webkit-search-cancel-button]:appearance-none"
                            placeholder="Cari data..."
                            aria-label="Cari pilihan data"
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
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2" role="listbox">
                    <div v-if="options.length === 0" class="px-3 py-3 text-sm font-medium text-slate-500">
                        {{ emptyText }}
                    </div>
                    <div v-else-if="filteredOptions.length === 0" class="px-3 py-6 text-center">
                        <p class="text-sm font-semibold text-slate-700">Data tidak ditemukan</p>
                        <p class="mt-1 text-xs text-slate-500">Gunakan nama indikator atau nama induknya.</p>
                    </div>
                    <div
                        v-for="group in groupedOptions"
                        v-else
                        :key="group.label || 'tanpa-kelompok'"
                        class="mb-2 last:mb-0"
                        role="group"
                        :aria-label="group.label || undefined"
                    >
                        <div
                            v-if="group.label"
                            class="break-words border-b border-slate-100 bg-slate-50/70 px-3 py-2 text-[11px] font-semibold uppercase text-slate-500"
                        >
                            {{ group.label }}
                        </div>
                        <button
                            v-for="option in group.options"
                            :key="String(optionValue(option))"
                            type="button"
                            class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2.5 text-left transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                sameValue(optionValue(option), modelValue)
                                    ? 'bg-[#00336C] text-white shadow-sm'
                                    : 'text-slate-700 hover:bg-blue-50 hover:text-slate-950'
                            "
                            :disabled="option.disabled"
                            role="option"
                            :aria-selected="sameValue(optionValue(option), modelValue)"
                            @click="selectOption(option)"
                        >
                            <span class="min-w-0">
                                <span class="block break-words text-sm font-semibold leading-5">{{ option.label }}</span>
                                <span
                                    v-if="option.description && option.description !== group.label"
                                    class="mt-0.5 block break-words text-xs font-medium leading-4"
                                    :class="sameValue(optionValue(option), modelValue) ? 'text-blue-100' : 'text-slate-500'"
                                >
                                    {{ option.description }}
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2 pt-0.5">
                                <span
                                    v-if="option.badge"
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="sameValue(optionValue(option), modelValue) ? 'bg-white/15 text-white' : 'bg-blue-50 text-[#00336C]'"
                                >
                                    {{ option.badge }}
                                </span>
                                <Check v-if="sameValue(optionValue(option), modelValue)" class="size-4 text-white" />
                            </span>
                        </button>
                    </div>
                </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
