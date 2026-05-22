<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
import InvitationAcceptanceForm from '@/Components/Invitations/InvitationAcceptanceForm.vue';

defineProps({
    invitation: Object,
});
</script>

<template>
    <GuestLayout>
        <Head title="Dokončiť registráciu" />

        <div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Pozvánka do firmy</p>
            <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                {{ invitation.mode === 'existing_user' ? 'Prihlásiť sa a prijať pozvánku' : 'Dokončiť registráciu' }}
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                Firma: <strong>{{ invitation.company_name }}</strong>
            </p>

            <InvitationAcceptanceForm
                :invitation="invitation"
                submit-route-name="company-invitations.accept.store"
            />
        </div>
    </GuestLayout>
</template>
