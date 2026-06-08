<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';

import Button from 'primevue/button';
import Select from 'primevue/select';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    messages: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            type: '',
            status: '',
            per_page: 15,
        }),
    },
});

const typeOptions = [
    {
        label: 'Kontaktný formulár',
        value: 'contact_form',
    },
    {
        label: 'Rezervácia',
        value: 'booking',
    },
    {
        label: 'Žiadosť o rezerváciu',
        value: 'appointment_request',
    },
];

const statusOptions = [
    {
        label: 'Neprečítané',
        value: 'unread',
    },
    {
        label: 'Prečítané',
        value: 'read',
    },
];

const typeLabel = (type) => {
    return {
        contact_form: 'Kontaktný formulár',
        booking: 'Rezervácia',
        appointment_request: 'Žiadosť o rezerváciu',
    }[type] ?? 'Správa';
};

const statusLabel = (message) => {
    return message.read_at ? 'Prečítaná' : 'Nová';
};

const statusClass = (message) => {
    return message.read_at
        ? 'bg-soft text-accent'
        : 'bg-dark text-soft';
};

const senderLabel = (message) => {
    return message.sender_name
        || message.sender_email
        || message.sender_phone
        || 'Neznámy odosielateľ';
};

const createdLabel = (message) => {
    if (!message.created_at) {
        return '—';
    }

    return new Date(message.created_at).toLocaleString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const rows = computed(() => {
    return (props.messages.data ?? []).map((message) => ({
        ...message,
        sender_label: senderLabel(message),
        type_label: typeLabel(message.type),
        status_label: statusLabel(message),
        created_label: createdLabel(message),
    }));
});

const columns = [
    {
        field: 'sender_label',
        header: 'Odosielateľ',
        sortable: true,
    },
    {
        field: 'type_label',
        header: 'Typ správy',
        sortable: true,
    },
    {
        field: 'status_label',
        header: 'Stav',
        sortable: true,
    },
    {
        field: 'created_label',
        header: 'Dátum',
        sortable: true,
    },
];

const normalizedFilters = computed(() => {
    return {
        type: props.filters.type ?? '',
        status: props.filters.status ?? '',
        per_page: props.filters.per_page ?? props.messages.per_page ?? 15,
    };
});

const applyFilters = (changes = {}) => {
    router.get(
        route('branches.inbox.index', props.branch.id),
        {
            ...normalizedFilters.value,
            ...changes,
            page: 1,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const changePage = ({ page, perPage }) => {
    router.get(
        route('branches.inbox.index', props.branch.id),
        {
            ...normalizedFilters.value,
            page,
            per_page: perPage,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const openMessage = (message) => {
    router.get(route('branches.inbox.show', [props.branch.id, message.id]));
};

const toggleRead = (message) => {
    const routeName = message.read_at
        ? 'branches.inbox.unread'
        : 'branches.inbox.read';

    router.patch(
        route(routeName, [props.branch.id, message.id]),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const deleteMessage = (message) => {
    router.delete(
        route('branches.inbox.destroy', [props.branch.id, message.id]),
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const refreshMessages = () => {
    router.reload({
        only: [
            'messages',
            'filters',
        ],
        preserveScroll: true,
    });
};

onMounted(() => {
    window.Echo
        .private(`branches.${props.branch.id}.inbox`)
        .listen('.inbox.updated', () => {
            refreshMessages();
        });
});

onBeforeUnmount(() => {
    window.Echo?.leave(`branches.${props.branch.id}.inbox`);
});
</script>

<template>
    <AdminLayout>
        <Head :title="`Správy | ${branch.name}`" />

        <div class="space-y-6">
            <TableCard
                title="Správy"
                description="Prehľad všetkých správ pre túto pobočku."
                :rows="rows"
                :columns="columns"
                :pagination="messages"
                :search-fields="['sender_label', 'type_label', 'status_label', 'created_label']"
                empty-message="Zatiaľ tu nie sú žiadne správy."
                show-row-actions
                @page="changePage"
            >
                <template #actions>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Select
                            :model-value="normalizedFilters.type || null"
                            :options="typeOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Typ správy"
                            show-clear
                            class="w-full sm:w-52"
                            @update:model-value="applyFilters({ type: $event ?? '' })"
                        />

                        <Select
                            :model-value="normalizedFilters.status || null"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Stav správy"
                            show-clear
                            class="w-full sm:w-52"
                            @update:model-value="applyFilters({ status: $event ?? '' })"
                        />
                    </div>
                </template>

                <template #cell-sender_label="{ row }">
                    <span class="text-sm font-semibold text-dark">
                        {{ row.sender_label }}
                    </span>
                </template>

                <template #cell-type_label="{ row }">
                    <span class="text-normal text-accent">
                        {{ row.type_label }}
                    </span>
                </template>

                <template #cell-status_label="{ row }">
                    <span
                        class="inline-flex rounded-md px-3 py-1 text-xs font-semibold"
                        :class="statusClass(row)"
                    >
                        {{ row.status_label }}
                    </span>
                </template>

                <template #cell-created_label="{ row }">
                    <span class="text-normal text-accent">
                        {{ row.created_label }}
                    </span>
                </template>

                <template #row-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Button
                            v-tooltip.top="'Otvoriť'"
                            icon="pi pi-eye"
                            size="small"
                            rounded
                            text
                            severity="secondary"
                            aria-label="Otvoriť správu"
                            @click="openMessage(row)"
                        />

                        <Button
                            v-tooltip.top="row.read_at ? 'Označiť ako neprečítané' : 'Označiť ako prečítané'"
                            :icon="row.read_at ? 'pi pi-envelope' : 'pi pi-check-circle'"
                            size="small"
                            rounded
                            text
                            severity="secondary"
                            :aria-label="row.read_at ? 'Označiť ako neprečítané' : 'Označiť ako prečítané'"
                            @click="toggleRead(row)"
                        />

                        <Button
                            v-tooltip.top="'Zmazať'"
                            icon="pi pi-trash"
                            size="small"
                            rounded
                            text
                            severity="danger"
                            aria-label="Zmazať správu"
                            @click="deleteMessage(row)"
                        />
                    </div>
                </template>
            </TableCard>
        </div>
    </AdminLayout>
</template>