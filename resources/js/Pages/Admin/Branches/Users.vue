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
    branch: {
        type: Object,
        required: true,
    },
    availableUsers: {
        type: Array,
        default: () => [],
    },
});

const inviteForm = useForm({
    invite_email: '',
});

const page = usePage();

const currentUserRole = computed(() => {
    return page.props.auth?.user?.global_role ?? null;
});

const currentUserEmail = computed(() => {
    return page.props.auth?.user?.email?.toLowerCase() ?? '';
});

const availableUsersByEmail = computed(() => {
    return new Map((props.availableUsers ?? []).map((user) => [
        user.email.toLowerCase(),
        user,
    ]));
});

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const displayName = (user) => {
    return [user.first_name, user.last_name].filter(Boolean).join(' ') || user.name || user.email || '—';
};

const initials = (user) => {
    return displayName(user)
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();
};

const branchAdmins = computed(() => {
    return (props.branch.users ?? [])
        .filter((user) => user.pivot?.role === 'branch_admin')
        .map((user) => {
            const status = user.pivot?.is_active ? 'Aktívny' : 'Neaktívny';

            return {
                type: 'user',
                sourceType: 'branch',
                id: user.id,
                userId: user.id,
                name: displayName(user),
                initials: initials(user),
                email: user.email,
                invitedByLabel: null,
                expiresAt: null,
                source: 'Pobočka',
                role: 'Branch admin',
                status,
                canResend: false,
                canDelete: ['super_admin', 'admin'].includes(currentUserRole.value),
            };
        });
});

const companyAdmins = computed(() => {
    return (props.branch.company?.users ?? [])
        .filter((user) => user.global_role === 'admin')
        .map((user) => {
            const status = user.is_active ? 'Aktívny' : 'Neaktívny';

            return {
                type: 'user',
                sourceType: 'company',
                id: `company-${user.id}`,
                userId: user.id,
                name: displayName(user),
                initials: initials(user),
                email: user.email,
                invitedByLabel: null,
                expiresAt: null,
                source: 'Firma',
                role: 'Company admin',
                status,
                canResend: false,
                canDelete: false,
            };
        });
});

const users = computed(() => {
    const seen = new Set();

    return [...companyAdmins.value, ...branchAdmins.value].filter((user) => {
        const dedupeKey = user.userId ?? user.id;

        if (seen.has(dedupeKey)) {
            return false;
        }

        seen.add(dedupeKey);

        return true;
    });
});

const invitations = computed(() => {
    return (props.branch.branch_invitations ?? []).map((invitation) => {
        const isExpired = invitation.expires_at
            ? new Date(invitation.expires_at).getTime() < Date.now()
            : false;

        const status = isExpired ? 'Vypršala' : 'Čaká na prijatie';

        return {
            type: 'invitation',
            sourceType: 'branch_invitation',
            id: invitation.id,
            userId: null,
            name: invitation.email,
            initials: null,
            email: invitation.email,
            invitedByLabel: invitation.invited_by ? displayName(invitation.invited_by) : '—',
            expiresAt: invitation.expires_at,
            source: 'Pozvánka',
            role: 'Branch admin',
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

const inviteBranchAdmin = () => {
    const email = inviteForm.invite_email?.toLowerCase() ?? '';

    inviteForm.clearErrors();

    if (email === currentUserEmail.value) {
        inviteForm.setError('invite_email', 'Nemôžeš pozvať samého seba ako branch admina.');

        return;
    }

    inviteForm.post(route('branches.users.store', props.branch.id), {
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
        message: `Odobrať používateľa ${user.name} z pobočky?`,
        confirmLabel: 'Odobrať',
        confirmSeverity: 'danger',
        icon: 'pi pi-trash',
        onConfirm: () => {
            router.delete(route('branches.users.destroy', [props.branch.id, user.userId ?? user.id]), {
                preserveScroll: true,
            });
        },
    });
};

const resendInvitation = (invitation) => {
    router.post(route('branches.invitations.resend', [props.branch.id, invitation.id]), {}, {
        preserveScroll: true,
    });
};

const deleteInvitation = (invitation) => {
    openDialog({
        title: 'Odstrániť pozvánku',
        message: `Odstrániť pozvánku pre ${invitation.email}?`,
        confirmLabel: 'Odstrániť',
        confirmSeverity: 'danger',
        icon: 'pi pi-trash',
        onConfirm: () => {
            router.delete(route('branches.invitations.destroy', [props.branch.id, invitation.id]), {
                preserveScroll: true,
            });
        },
    });
};
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <form @submit.prevent="inviteBranchAdmin">
                <InvitationFormSection
                    :form="inviteForm"
                    title="Pozvať používateľa do pobočky"
                    description="Pozvite používateľa, aby vedel spravovať túto pobočlku."
                    input-label="Email používateľa"
                    submit-label="Poslať pozvánku"
                    :loading="inviteForm.processing"
                />
            </form>

            <TableCard
                title="Používatelia a pozvánky"
                description="Používatelia s prístupom do pobočky a čakajúce pozvánky."
                :rows="combinedRows"
                :columns="columns"
                empty-message="Táto pobočka zatiaľ nemá žiadnych používateľov ani pozvánky."
                show-row-actions
            >
                <template #cell-name="{ row }">
                    <div
                        v-if="row.type === 'user'"
                        class="flex items-center gap-3"
                    >
                        <Avatar
                            :label="row.initials"
                            shape="circle"
                        />

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-dark">
                                {{ row.name }}
                            </p>

                            <p class="truncate text-sm text-accent/70">
                                {{ row.email }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="min-w-0"
                    >
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

                        <div
                            v-if="row.type === 'invitation'"
                            class="flex items-center gap-2"
                        >
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