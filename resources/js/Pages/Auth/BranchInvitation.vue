<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InvitationAcceptanceForm from '@/Components/Invitations/InvitationAcceptanceForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    invitation: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <GuestLayout>
        <Head title="Dokončiť registráciu" />

        <div class="w-full max-w-md space-y-6">
            <div class="space-y-3 text-center">
                <p class="text-normal text-accent">
                    Pridajte sa k spravovaniu pobočky
                </p>

                <h1 class="text-heading text-accent">
                    {{ invitation.entity_name }}
                </h1>
            </div>

            <InvitationAcceptanceForm
                :invitation="invitation"
                submit-route-name="branch-invitations.accept.store"
            />
        </div>
    </GuestLayout>
</template>