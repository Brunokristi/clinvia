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
    if (!message.created_at) {
        return '—';
    }

    return new Date(message.created_at).toLocaleString('sk-SK');
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

const paginationLinks = computed(() => {
    return props.messages.links ?? [];
});

const hasPagination = computed(() => {
    return paginationLinks.value.length > 3;
});

const normalizedFilters = computed(() => {
    return {
        type: props.filters.type ?? '',
        status: props.filters.status ?? '',
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

const goToPage = (url) => {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
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
            preserveState: false,
        },
    );
};

const deleteMessage = (message) => {
    router.delete(
        route('branches.inbox.destroy', [props.branch.id, message.id]),
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Inbox | ${branch.name}`" />

        <div class="space-y-6">
            <TableCard
                title="Správy"
                description="Prehľad všetkých správ pre túto pobočku."
                :rows="rows"
                :columns="columns"
                :paginator="false"
                :search-fields="['sender_label', 'sender_email', 'sender_phone', 'type_label', 'title', 'body', 'status_label', 'created_label']"
                empty-message="Zatiaľ tu nie sú žiadne správy."
                show-row-actions
            >
                <template #actions>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Select
                            :model-value="normalizedFilters.type"
                            :options="typeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full sm:w-52"
                            @update:model-value="applyFilters({ type: $event })"
                        />

                        <Select
                            :model-value="normalizedFilters.status"
                            :options="statusOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full sm:w-52"
                            @update:model-value="applyFilters({ status: $event })"
                        />
                    </div>
                </template>

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
                            :icon="row.read_at ? 'pi pi-envelope' : 'pi pi-envelope-open'"
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

                <template #footer>
                    <div
                        v-if="hasPagination"
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-sm text-accent">
                            Zobrazené {{ messages.from ?? 0 }} – {{ messages.to ?? 0 }} z {{ messages.total ?? 0 }}
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="link in paginationLinks"
                                :key="link.label"
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm transition"
                                :class="[
                                    link.active
                                        ? 'border-accent bg-accent text-white'
                                        : 'border-soft bg-white text-accent hover:bg-soft',
                                    !link.url ? 'cursor-not-allowed opacity-50' : '',
                                ]"
                                :disabled="!link.url"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </template>
            </TableCard>
        </div>
    </AdminLayout>
</template>