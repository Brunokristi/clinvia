<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

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
        }),
    },
});

const typeOptions = [
    {
        label: 'Všetky typy',
        value: '',
    },
    {
        label: 'Kontaktný formulár',
        value: 'contact_form',
    },
    {
        label: 'Chat',
        value: 'chat',
    },
    {
        label: 'Rezervácia',
        value: 'booking',
    },
];

const statusOptions = [
    {
        label: 'Všetky správy',
        value: '',
    },
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
        chat: 'Chat',
        booking: 'Rezervácia',
    }[type] ?? type;
};

const statusLabel = (message) => {
    return message.read_at ? 'Prečítaná' : 'Nová';
};

const statusClass = (message) => {
    return message.read_at
        ? 'bg-green-50 text-green-700'
        : 'bg-blue-50 text-blue-700';
};

const senderLabel = (message) => {
    return message.sender_name
        || message.sender_email
        || message.sender_phone
        || 'Neznámy odosielateľ';
};

const createdLabel = (message) => {
    return new Date(message.created_at).toLocaleString('sk-SK');
};

const rows = computed(() => {
    return props.messages.data.map((message) => ({
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
        header: 'Typ',
        sortable: true,
    },
    {
        field: 'title',
        header: 'Predmet',
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

const applyFilters = (changes = {}) => {
    router.get(
        route('branches.inbox.index', props.branch.id),
        {
            type: props.filters.type,
            status: props.filters.status,
            ...changes,
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

const markAsRead = (message) => {
    router.patch(
        route('branches.inbox.read', [props.branch.id, message.id]),
        {},
        {
            preserveScroll: true,
        },
    );
};

const deleteMessage = (message) => {
    router.delete(
        route('branches.inbox.destroy', [props.branch.id, message.id]),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Inbox | ${branch.name}`" />

        <div class="space-y-6">
            <div>
                <h1 class="text-heading font-semibold text-dark">
                    Inbox
                </h1>

                <p class="mt-2 text-normal text-accent">
                    Správy z kontaktného formulára, rezervácií a budúce chatové správy pre pobočku {{ branch.name }}.
                </p>
            </div>

            <div class="grid gap-4 rounded-md bg-white p-4 md:grid-cols-2">
                <Select
                    :model-value="filters.type"
                    :options="typeOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Typ správy"
                    @update:model-value="applyFilters({ type: $event })"
                />

                <Select
                    :model-value="filters.status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Stav"
                    @update:model-value="applyFilters({ status: $event })"
                />
            </div>

            <TableCard
                title="Správy"
                description="Prehľad všetkých správ pre túto pobočku."
                :rows="rows"
                :columns="columns"
                empty-message="Zatiaľ tu nie sú žiadne správy."
                show-row-actions
            >
                <template #cell-sender_label="{ row }">
                    <div>
                        <p class="text-sm font-semibold text-dark">
                            {{ row.sender_label }}
                        </p>

                        <p
                            v-if="row.sender_email"
                            class="text-xs text-accent/70"
                        >
                            {{ row.sender_email }}
                        </p>

                        <p
                            v-if="row.sender_phone"
                            class="text-xs text-accent/70"
                        >
                            {{ row.sender_phone }}
                        </p>
                    </div>
                </template>

                <template #cell-title="{ row }">
                    <div>
                        <p class="text-sm font-medium text-dark">
                            {{ row.title || 'Bez predmetu' }}
                        </p>

                        <p class="mt-1 line-clamp-1 text-xs text-accent/70">
                            {{ row.body }}
                        </p>
                    </div>
                </template>

                <template #cell-status_label="{ row }">
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                        :class="statusClass(row)"
                    >
                        {{ row.status_label }}
                    </span>
                </template>

                <template #row-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Otvoriť"
                            size="small"
                            @click="openMessage(row)"
                        />

                        <Button
                            v-if="!row.read_at"
                            label="Prečítané"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="markAsRead(row)"
                        />

                        <Button
                            label="Zmazať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteMessage(row)"
                        />
                    </div>
                </template>
            </TableCard>
        </div>
    </AdminLayout>
</template>