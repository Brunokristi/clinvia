<script setup>
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { FilterMatchMode } from '@primevue/core/api';
import { computed, ref, watch } from 'vue';

const props = defineProps({
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
    pagination: {
        type: Object,
        default: null,
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
    rowsPerPageOptions: {
        type: Array,
        default: () => [10, 20, 25, 50, 100],
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
    searchValue: {
        type: String,
        default: '',
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

const emit = defineEmits(['page', 'search']);

const globalSearch = ref(props.searchValue);

const filters = ref({
    global: {
        value: null,
        matchMode: FilterMatchMode.CONTAINS,
    },
});

const currentPage = computed(() => {
    return props.pagination?.current_page ?? 1;
});

const perPage = computed(() => {
    return props.pagination?.per_page ?? 20;
});

const first = computed(() => {
    return (currentPage.value - 1) * perPage.value;
});

const totalRecords = computed(() => {
    return props.pagination?.total ?? props.rows.length;
});

watch(
    () => props.searchValue,
    (value) => {
        globalSearch.value = value;
    },
);

watch(globalSearch, (value) => {
    filters.value.global.value = value;

    emit('search', value ?? '');
});

const onPage = (event) => {
    emit('page', {
        page: event.page + 1,
        perPage: event.rows,
    });
};

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
        <div class="">
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
                v-if="showSearch || $slots.actions"
                class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-end"
            >
                <IconField
                    v-if="showSearch"
                    class="w-full sm:max-w-md"
                >
                    <InputIcon class="pi pi-search" />

                    <InputText
                        v-model="globalSearch"
                        class="w-full"
                        :aria-label="searchLabel"
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
                paginator
                class="table-card-datatable"
                :value="rows"
                :dataKey="rowKey"
                :tableStyle="tableStyle"
                :first="first"
                :rows="perPage"
                :totalRecords="totalRecords"
                :rowsPerPageOptions="rowsPerPageOptions"
                paginatorTemplate="CurrentPageReport PrevPageLink PageLinks NextPageLink RowsPerPageDropdown"
                currentPageReportTemplate="Zobrazené {first} – {last} z {totalRecords}"
                :removableSort="removableSort"
                :stripedRows="stripedRows"
                :rowHover="rowHover"
                :emptyMessage="emptyMessage"
                v-model:filters="filters"
                :globalFilterFields="searchFields"
                @page="onPage"
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
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <slot
                                name="row-actions"
                                :row="data"
                            />
                        </div>
                    </template>
                </Column>

                <template #empty>
                    <div class="p-8 text-center text-normal text-accent">
                        {{ emptyMessage }}
                    </div>
                </template>
            </DataTable>
        </div>
    </section>
</template>
