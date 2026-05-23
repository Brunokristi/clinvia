<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import EntityUsersPanel from '@/Components/UserManagement/EntityUsersPanel.vue';
import InvitationFormSection from '@/Components/Invitations/InvitationFormSection.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branch: Object,
    availableUsers: {
        type: Array,
        default: () => [],
    },
});

const inviteForm = useForm({
    invite_email: '',
});

const page = usePage();
const currentUserRole = computed(() => page.props.auth?.user?.global_role ?? null);
const currentUserEmail = computed(() => page.props.auth?.user?.email?.toLowerCase() ?? '');
const availableUsersByEmail = computed(() => {
    return new Map((props.availableUsers ?? []).map((user) => [user.email.toLowerCase(), user]));
});
const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const userDisplayName = (user) => {
    return [user.first_name, user.last_name].filter(Boolean).join(' ') || user.name || '—';
};

const userInitials = (user) => userDisplayName(user)
    .split(' ')
    .map((part) => part.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase();

const branchAdmins = computed(() => {
    return (props.branch.users ?? [])
        .filter((user) => user.pivot?.role === 'branch_admin')
        .map((user) => ({
            userId: user.id,
            id: user.id,
            name: userDisplayName(user),
            initials: userInitials(user),
            email: user.email,
            sourceLabel: 'Pobočka',
            sourceSeverity: 'success',
            roleLabel: 'Admin',
            roleSeverity: 'success',
            statusLabel: user.pivot?.is_active ? 'Aktívny' : 'Neaktívny',
            statusSeverity: user.pivot?.is_active ? 'success' : 'secondary',
            canDelete: ['super_admin', 'admin'].includes(currentUserRole.value),
        }));
});

const companyAdmins = computed(() => {
    return (props.branch.company?.users ?? [])
        .filter((user) => user.global_role === 'admin')
        .map((user) => ({
            userId: user.id,
            id: `company-${user.id}`,
            name: userDisplayName(user),
            initials: userInitials(user),
            email: user.email,
            sourceLabel: 'Firma',
            sourceSeverity: 'info',
            roleLabel: 'Admin',
            roleSeverity: 'info',
            statusLabel: user.is_active ? 'Aktívny' : 'Neaktívny',
            statusSeverity: user.is_active ? 'success' : 'secondary',
            canDelete: false,
        }));
});

const adminUsers = computed(() => {
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

const branchInvitations = computed(() => (props.branch.branch_invitations ?? []).map((invitation) => {
    const isExpired = invitation.expires_at ? new Date(invitation.expires_at).getTime() < Date.now() : false;

    return {
        id: invitation.id,
        email: invitation.email,
        invitedByLabel: invitation.invited_by ? userDisplayName(invitation.invited_by) : '—',
        expiresAt: invitation.expires_at,
        statusLabel: isExpired ? 'Vypršala' : 'Čaká na prijatie',
        statusSeverity: isExpired ? 'danger' : 'warning',
        canResend: isExpired,
        canDelete: true,
    };
}));

const inviteBranchAdmin = () => {
    if (inviteForm.invite_email?.toLowerCase() === currentUserEmail.value) {
        inviteForm.setError('invite_email', 'Nemôžeš pozvať samého seba ako branch admina.');

        return;
    }

    const matchedUser = availableUsersByEmail.value.get(inviteForm.invite_email?.toLowerCase() ?? '');

    if (matchedUser && ['super_admin', 'admin'].includes(matchedUser.global_role)) {
        inviteForm.setError('invite_email', 'Admina nemožno pozvať ako branch admina. Pošli mu pozvánku do firmy.');

        return;
    }

    inviteForm.post(route('branches.users.store', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset('invite_email');
        },
    });
};

const detachBranchUser = (user) => {
    openDialog({
        title: 'Odobrať používateľa',
        message: `Odstrániť používateľa ${userDisplayName(user)} z tejto pobočky?`,
        confirmLabel: 'Odobrať',
        onConfirm: () => {
            router.delete(route('branches.users.destroy', [props.branch.id, user.id]), {
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
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                    Pobočka
                </p>

                <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                    Pozvánky do pobočky
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Pošlite pozvánku na email. Ak už používateľ existuje, priradí sa ako branch admin. Ak nie, vytvorí si účet po prijatí pozvánky.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">
                    Aktívna pobočka
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ branch.name }}
                </p>
            </div>
        </div>

        <EntityUsersPanel
            title="Admini pobočky a spoločnosti"
            description="Tu vidíš branch adminov aj company adminov, ktorí majú prístup cez spoločnosť, a pozvánky do pobočky."
            :users="adminUsers"
            :invitations="branchInvitations"
            users-empty-message="Táto pobočka zatiaľ nemá žiadnych adminov."
            invitations-empty-message="Táto pobočka zatiaľ nemá žiadne pozvánky."
            @delete-user="detachBranchUser"
            @resend-invitation="resendInvitation"
            @delete-invitation="deleteInvitation"
        >
            <template #invite-form>
                <form class="mt-5" @submit.prevent="inviteBranchAdmin">
                    <InvitationFormSection
                        :form="inviteForm"
                        title="Pozvať branch admina"
                        description="Pozvánka ide na email a po prijatí sa používateľ priradí s rolou branch_admin."
                        input-label="Email branch admina"
                        submit-label="Poslať pozvánku"
                        :loading="inviteForm.processing"
                    />
                </form>
            </template>
        </EntityUsersPanel>

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
