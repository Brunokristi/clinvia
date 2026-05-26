<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { FilterMatchMode } from '@primevue/core/api';
import { ref, watch } from 'vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    rows: {
        type: Array,
        default: () => [],
    },
    columns: {
        type: Array,
        default: () => [],
    },
    emptyMessage: {
        type: String,
        default: 'Zatiaľ tu nie sú žiadne záznamy.',
    },
    rowKey: {
        type: String,
        default: 'id',
    },
    tableStyle: {
        type: String,
        default: 'min-width: 40rem',
    },
    paginator: {
        type: Boolean,
        default: true,
    },
    rowsPerPageOptions: {
        type: Array,
        default: () => [10, 25, 50, 100],
    },
    removableSort: {
        type: Boolean,
        default: true,
    },
    stripedRows: {
        type: Boolean,
        default: true,
    },
    rowHover: {
        type: Boolean,
        default: true,
    },
    showRowActions: {
        type: Boolean,
        default: false,
    },
    rowActionsHeader: {
        type: String,
        default: 'Akcie',
    },
    rowActionsStyle: {
        type: String,
        default: 'width: 180px',
    },
    searchFields: {
        type: Array,
        default: () => [],
    },
    searchPlaceholder: {
        type: String,
        default: 'Hľadať',
    },
    searchLabel: {
        type: String,
        default: 'Hľadať v tabuľke',
    },
    showSearch: {
        type: Boolean,
        default: true,
    },
});

const globalSearch = ref(null);

const filters = ref({
    global: {
        value: null,
        matchMode: FilterMatchMode.CONTAINS,
    },
});

watch(globalSearch, (value) => {
    filters.value.global.value = value;
});

const resolveCellValue = (row, column) => {
    const value = row?.[column.field];

    if (value === null || value === undefined || value === '') {
        return column.emptyValue ?? '—';
    }

    return value;
};

</script>

<template>
    <section>
        <div class="border-b border-slate-200">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-normal font-semibold text-dark">
                        {{ title }}
                    </h2>

                    <p
                        v-if="description"
                        class="mt-1 text-normal text-accent"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>

            <div
                v-if="showSearch"
                class="py-4 flex flex-col gap-3 sm:flex-row justify-end"
            >
                <IconField class="w-full sm:max-w-md">
                    <InputIcon class="pi pi-search" />
                    <InputText
                        v-model="globalSearch"
                        class="w-full"
                        :placeholder="searchPlaceholder"
                    />
                </IconField>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-center gap-3"
                >
                    <slot name="actions" />
                </div>
            </div>
        </div>

        <div class="overflow-hidden">
            <DataTable
                v-if="rows.length > 0"
                :value="rows"
                :dataKey="rowKey"
                :tableStyle="tableStyle"
                :paginator="paginator"
                :rows="20"
                :rowsPerPageOptions="rowsPerPageOptions"
                :removableSort="removableSort"
                :stripedRows="stripedRows"
                :rowHover="rowHover"
                :emptyMessage="emptyMessage"
                v-model:filters="filters"
                :globalFilterFields="searchFields"
            >
                <Column
                    v-for="column in columns"
                    :key="column.field ?? column.header"
                    :field="column.field"
                    :header="column.header"
                    :sortable="column.sortable ?? false"
                    :style="column.style"
                    :headerStyle="column.headerStyle"
                    :bodyStyle="column.bodyStyle"
                    :headerClass="column.headerClass"
                    :bodyClass="column.bodyClass"
                >
                    <template #body="{ data }">
                        <slot
                            :name="`cell-${column.field}`"
                            :row="data"
                            :column="column"
                        >
                            <span class="text-normal text-accent">
                                {{ resolveCellValue(data, column) }}
                            </span>
                        </slot>
                    </template>
                </Column>

                <Column
                    v-if="showRowActions && $slots['row-actions']"
                    :header="rowActionsHeader"
                    :style="rowActionsStyle"
                >
                    <template #body="{ data }">
                        <slot
                            name="row-actions"
                            :row="data"
                        />
                    </template>
                </Column>

                <template #paginatorcontainer="{ first, last, page, pageCount, prevPageCallback, nextPageCallback, totalRecords }">
                    <div class="flex items-center justify-between gap-4 bg-transparentw w-full py-1">
                        <Button icon="pi pi-chevron-left" class="!text-xs" text @click="prevPageCallback" :disabled="page === 0" />
                        <div class="text-color text-normal font-semibold w-full min-w-[500px] text-center">
                            <span class="hidden sm:block">Zobrazujú sa záznamy od {{ first }} do {{ last }} z {{ totalRecords }}</span>
                            <span class="block sm:hidden">Stánka {{ page + 1 }} z {{ pageCount }}</span>
                        </div>
                        <Button icon="pi pi-chevron-right" class="!text-xs" text @click="nextPageCallback" :disabled="page === pageCount - 1" />
                    </div>
                </template>
            </DataTable>

            <div
                v-else
                class="p-8 text-center text-normal text-accent"
            >
                {{ emptyMessage }}
            </div>
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-slate-200 p-6"
        >
            <slot name="footer" />
        </div>
    </section>
</template>