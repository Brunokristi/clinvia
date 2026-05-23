<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    users: {
        type: Array,
        default: () => [],
    },
    invitations: {
        type: Array,
        default: () => [],
    },
    usersEmptyMessage: {
        type: String,
        default: 'Zatiaľ tu nie sú žiadni používatelia.',
    },
    invitationsEmptyMessage: {
        type: String,
        default: 'Zatiaľ tu nie sú žiadne pozvánky.',
    },
});

const emit = defineEmits([
    'delete-user',
    'resend-invitation',
    'delete-invitation',
]);

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('sk-SK', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ title }}
                    </h2>

                    <p v-if="description" class="mt-1 text-sm text-slate-600">
                        {{ description }}
                    </p>
                </div>

                <Tag
                    :value="`${users.length} používateľov · ${invitations.length} pozvánok`"
                    severity="secondary"
                />
            </div>

            <slot name="invite-form" />
        </div>

        <div class="border-b border-slate-200 p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">
                    Aktívni používatelia
                </h3>
            </div>

            <DataTable
                :value="users"
                tableStyle="min-width: 56rem"
                :emptyMessage="usersEmptyMessage"
            >
                <Column header="Používateľ">
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-700">
                                {{ data.initials }}
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ data.name }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    {{ data.email }}
                                </p>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column header="Zdroj">
                    <template #body="{ data }">
                        <Tag
                            v-if="data.sourceLabel"
                            :value="data.sourceLabel"
                            :severity="data.sourceSeverity ?? 'secondary'"
                        />

                        <span v-else class="text-sm text-slate-400">
                            —
                        </span>
                    </template>
                </Column>

                <Column header="Rola">
                    <template #body="{ data }">
                        <Tag
                            :value="data.roleLabel ?? '—'"
                            :severity="data.roleSeverity ?? 'secondary'"
                        />
                    </template>
                </Column>

                <Column header="Stav">
                    <template #body="{ data }">
                        <Tag
                            :value="data.statusLabel ?? '—'"
                            :severity="data.statusSeverity ?? 'secondary'"
                        />
                    </template>
                </Column>

                <Column header="Akcie">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="data.canDelete"
                                label="Odobrať"
                                size="small"
                                severity="danger"
                                outlined
                                icon="pi pi-trash"
                                @click="emit('delete-user', data)"
                            />

                            <Tag
                                v-else
                                value="Bez akcie"
                                severity="secondary"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">
                    Pozvánky
                </h3>
            </div>

            <DataTable
                :value="invitations"
                tableStyle="min-width: 56rem"
                :emptyMessage="invitationsEmptyMessage"
            >
                <Column header="Email">
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-700">
                                <i class="pi pi-envelope text-xs" />
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ data.email }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    {{ data.invitedByLabel ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column header="Stav">
                    <template #body="{ data }">
                        <Tag
                            :value="data.statusLabel ?? '—'"
                            :severity="data.statusSeverity ?? 'secondary'"
                        />
                    </template>
                </Column>

                <Column header="Platnosť do">
                    <template #body="{ data }">
                        <span class="text-sm text-slate-700">
                            {{ formatDateTime(data.expiresAt) }}
                        </span>
                    </template>
                </Column>

                <Column header="Akcie">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="data.canResend"
                                label="Odoslať znova"
                                size="small"
                                severity="secondary"
                                outlined
                                icon="pi pi-refresh"
                                @click="emit('resend-invitation', data)"
                            />

                            <Button
                                v-if="data.canDelete"
                                label="Odstrániť"
                                size="small"
                                severity="danger"
                                outlined
                                icon="pi pi-trash"
                                @click="emit('delete-invitation', data)"
                            />

                            <Tag
                                v-if="!data.canResend && !data.canDelete"
                                value="Bez akcie"
                                severity="secondary"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </section>
</template>