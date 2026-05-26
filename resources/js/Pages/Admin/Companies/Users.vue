<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import InvitationFormSection from '@/Components/Invitations/InvitationFormSection.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import Avatar from 'primevue/avatar';
import Button from 'primevue/button';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
});

const inviteForm = useForm({
    invite_email: '',
});

const page = usePage();

const currentUserRole = computed(() => page.props.auth?.user?.global_role ?? null);

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const displayName = (user) => {
    return [user.first_name, user.last_name].filter(Boolean).join(' ') || user.email || '—';
};

const initials = (user) => {
    return displayName(user)
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();
};

const users = computed(() => {
    return (props.company.users ?? []).map((user) => {
        const role = user.pivot?.role === 'company_admin'
            ? 'Admin'
            : (user.pivot?.role ?? '—');

        const status = user.pivot?.is_active ? 'Aktívny' : 'Neaktívny';

        return {
            type: 'user',
            id: user.id,
            name: displayName(user),
            initials: initials(user),
            email: user.email,
            invitedByLabel: null,
            source: 'Firma',
            role,
            status,
            canResend: false,
            canDelete: currentUserRole.value === 'super_admin' || user.pivot?.role !== 'company_admin',
        };
    });
});

const invitations = computed(() => {
    return (props.company.company_invitations ?? []).map((invitation) => {
        const isExpired = invitation.expires_at
            ? new Date(invitation.expires_at).getTime() < Date.now()
            : false;

        const status = isExpired ? 'Vypršala' : 'Čaká na prijatie';

        return {
            type: 'invitation',
            id: invitation.id,
            name: invitation.email,
            initials: null,
            email: invitation.email,
            invitedByLabel: invitation.invited_by ? displayName(invitation.invited_by) : '—',
            expiresAt: invitation.expires_at,
            source: 'Pozvánka',
            role: '—',
            status,
            canResend: isExpired,
            canDelete: true,
        };
    });
});

const combinedRows = computed(() => {
    return [...users.value, ...invitations.value];
});

const columns = [
    {
        field: 'name',
        header: 'Používateľ',
        sortable: true,
    },
    {
        field: 'source',
        header: 'Level',
        sortable: true,
    },
    {
        field: 'role',
        header: 'Rola',
        sortable: true,
    },
    {
        field: 'status',
        header: 'Stav',
        sortable: true,
    },
];

const inviteCompanyAdmin = () => {
    inviteForm.post(route('companies.users.store', props.company.id), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset('invite_email');
            router.reload();
        },
    });
};

const deleteUser = (user) => {
    openDialog({
        title: 'Odobrať používateľa',
        message: `Odobrať používateľa ${user.name} z firmy?`,
        confirmLabel: 'Odobrať',
        onConfirm: () => {
            router.delete(route('companies.users.destroy', [props.company.id, user.id]), {
                preserveScroll: true,
            });
        },
    });
};

const resendInvitation = (invitation) => {
    router.post(route('companies.invitations.resend', [props.company.id, invitation.id]), {}, {
        preserveScroll: true,
    });
};

const deleteInvitation = (invitation) => {
    openDialog({
        title: 'Odstrániť pozvánku',
        message: `Odstrániť pozvánku pre ${invitation.email}?`,
        confirmLabel: 'Odstrániť',
        onConfirm: () => {
            router.delete(route('companies.invitations.destroy', [props.company.id, invitation.id]), {
                preserveScroll: true,
            });
        },
    });
};
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <form @submit.prevent="inviteCompanyAdmin">
                <InvitationFormSection
                    :form="inviteForm"
                    title="Pozvať používateľa do firmy"
                    description="Pozvi ľubovoľného používateľa, aby sa stal adminom tejto firmy. Bude mať plný prístup ku všetkým pobočkám a nastaveniam firmy."
                    input-label="Email používateľa"
                    submit-label="Poslať pozvánku"
                    :loading="inviteForm.processing"
                />
            </form>

            <TableCard
                title="Používatelia a pozvánky"
                description="Aktívni používatelia a čakajúce pozvánky pre túto firmu."
                :rows="combinedRows"
                :columns="columns"
                empty-message="Táto firma zatiaľ nemá žiadne položky."
                show-row-actions
            >
                <template #cell-name="{ row }">
                    <div v-if="row.type === 'user'" class="flex items-center gap-3">
                        <Avatar :label="row.initials" shape="circle" />

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-dark">
                                {{ row.name }}
                            </p>

                            <p class="truncate text-sm text-accent/70">
                                {{ row.email }}
                            </p>
                        </div>
                    </div>

                    <div v-else class="min-w-0">
                        <p class="truncate text-sm font-semibold text-dark">
                            {{ row.email }}
                        </p>

                        <p class="truncate text-sm text-accent/70">
                            Pozval: {{ row.invitedByLabel }}
                        </p>
                    </div>
                </template>

                <template #cell-source="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.source }}
                    </span>
                </template>

                <template #cell-role="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.role }}
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.status }}
                    </span>
                </template>

                <template #row-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="row.type === 'user' && row.canDelete"
                            type="button"
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteUser(row)"
                        />

                        <div v-if="row.type === 'invitation'" class="flex items-center gap-2">
                            <Button
                                v-if="row.canResend"
                                type="button"
                                label="Znovu poslať"
                                icon="pi pi-send"
                                size="small"
                                outlined
                                @click="resendInvitation(row)"
                            />

                            <Button
                                v-if="row.canDelete"
                                type="button"
                                label="Odstrániť"
                                size="small"
                                severity="danger"
                                outlined
                                @click="deleteInvitation(row)"
                            />
                        </div>
                    </div>
                </template>
            </TableCard>
        </div>

        <ConfirmationDialog
            :show="dialog.visible"
            :title="dialog.title"
            :message="dialog.message"
            :confirm-label="dialog.confirmLabel"
            :cancel-label="dialog.cancelLabel"
            :confirm-severity="dialog.confirmSeverity"
            :icon="dialog.icon"
            @cancel="closeDialog"
            @confirm="confirmDialog"
        />
    </AdminLayout>
</template>