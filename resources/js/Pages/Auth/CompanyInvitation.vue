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
                <p class="text-normal">
                    Pridajte sa k spravovaniu firmy 
                </p>
                <h1 class="text-heading text-accent">  
                    {{ invitation.company_name }}
                </h1>
            </div>

            <InvitationAcceptanceForm
                :invitation="invitation"
                submit-route-name="company-invitations.accept.store"
            />
        </div>
    </GuestLayout>
</template>