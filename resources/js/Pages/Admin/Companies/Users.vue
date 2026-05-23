<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import EntityUsersPanel from '@/Components/UserManagement/EntityUsersPanel.vue';
import InvitationFormSection from '@/Components/Invitations/InvitationFormSection.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    company: Object,
});

const inviteForm = useForm({
    invite_email: '',
});

const page = usePage();
const currentUserRole = computed(() => page.props.auth?.user?.global_role ?? null);
const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const displayName = (user) => [user.first_name, user.last_name].filter(Boolean).join(' ') || user.email || '—';

const initials = (user) => displayName(user)
    .split(' ')
    .map((part) => part.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase();

const users = computed(() => (props.company.users ?? []).map((user) => ({
    id: user.id,
    name: displayName(user),
    initials: initials(user),
    email: user.email,
    sourceLabel: 'Firma',
    sourceSeverity: 'info',
    roleLabel: user.pivot?.role === 'company_admin' ? 'Admin' : (user.pivot?.role ?? '—'),
    roleSeverity: user.pivot?.role === 'company_admin' ? 'success' : 'secondary',
    statusLabel: user.pivot?.is_active ? 'Aktívny' : 'Neaktívny',
    statusSeverity: user.pivot?.is_active ? 'success' : 'secondary',
    canDelete: currentUserRole.value === 'super_admin' || user.pivot?.role !== 'company_admin',
})));

const invitations = computed(() => (props.company.company_invitations ?? []).map((invitation) => {
    const isExpired = invitation.expires_at ? new Date(invitation.expires_at).getTime() < Date.now() : false;

    return {
        id: invitation.id,
        email: invitation.email,
        invitedByLabel: invitation.invited_by ? displayName(invitation.invited_by) : '—',
        expiresAt: invitation.expires_at,
        statusLabel: isExpired ? 'Vypršala' : 'Čaká na prijatie',
        statusSeverity: isExpired ? 'danger' : 'warning',
        canResend: isExpired,
        canDelete: true,
    };
}));

const inviteCompanyAdmin = () => {
    inviteForm.post(route('companies.users.store', props.company.id), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset('invite_email');
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
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                    Firma
                </p>

                <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                    Používatelia firmy
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Spravuj používateľov priradených k firme a pozvánky, ktoré ešte neboli prijaté.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">
                    Aktívna firma
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ company.legal_name }}
                </p>
            </div>
        </div>

        <EntityUsersPanel
            title="Používatelia a pozvánky"
            description="Každý pozvaný používateľ získa prístup po prijatí pozvánky. Vypršané pozvánky vieš odoslať znova."
            :users="users"
            :invitations="invitations"
            users-empty-message="Táto firma zatiaľ nemá žiadnych používateľov."
            invitations-empty-message="Táto firma zatiaľ nemá žiadne pozvánky."
            @delete-user="deleteUser"
            @resend-invitation="resendInvitation"
            @delete-invitation="deleteInvitation"
        >
            <template #invite-form>
                <form class="mt-5" @submit.prevent="inviteCompanyAdmin">
                    <InvitationFormSection
                        :form="inviteForm"
                        title="Pozvať používateľa do firmy"
                        description="Pozvánka ide na email a po prijatí sa používateľ priradí k firme ako admin."
                        input-label="Email používateľa"
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