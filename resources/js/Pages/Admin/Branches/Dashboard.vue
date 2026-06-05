<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import { useBranchBroadcasting } from '@/Composables/useBranchBroadcasting';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    todayBookingsCount: {
        type: Number,
        default: 0,
    },
    pendingAppointmentRequestsCount: {
        type: Number,
        default: 0,
    },
    unreadMessagesCount: {
        type: Number,
        default: 0,
    },
    servicesCount: {
        type: Number,
        default: 0,
    },
    employeesCount: {
        type: Number,
        default: 0,
    },
    contactsCount: {
        type: Number,
        default: 0,
    },
    usersCount: {
        type: Number,
        default: 0,
    },
    todayAgenda: {
        type: Array,
        default: () => [],
    },
});

const branchTitle = computed(() => props.branch.name || 'Pobočka');
const companyName = computed(() => props.branch.company?.legal_name || 'Spoločnosť');

const reloadBranchPage = () => {
    router.reload({
        preserveState: true,
        preserveScroll: true,
    });
};

useBranchBroadcasting(props.branch.id, reloadBranchPage);

const overviewCards = computed(() => [
    {
        title: 'Dnešné rezervácie',
        value: props.todayBookingsCount,
        description: 'Počet rezervácií naplánovaných na dnešný deň.',
    },
    {
        title: 'Čakajúce žiadosti',
        value: props.pendingAppointmentRequestsCount,
        description: 'Žiadosti o rezerváciu, ktoré je potrebné spracovať.',
    },
    {
        title: 'Nečítané správy',
        value: props.unreadMessagesCount,
        description: 'Počet správ v inboxe, ktoré ešte neboli prečítané.',
    },
]);

const goToCalendar = () => {
    router.visit(route('branches.booking.agenda.page', {
        branch: props.branch.id,
    }));
};
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-1 xl:grid-cols-3">
                <article
                    v-for="card in overviewCards"
                    :key="card.title"
                    class="rounded-md bg-soft text-accent p-5"
                >
                    <p class="text-normal font-semibold text-accent">{{ card.title }}</p>
                    <p class="mt-4 text-heading text-dark font-semibold">{{ card.value }}</p>
                    <p class="mt-3 text-normal leading-6 text-muted">{{ card.description }}</p>
                </article>
            </section>

            <section>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-normal font-semibold text-dark">Dnešná agenda</h2>
                        <p class="mt-1 text-normal text-accent">
                            Prehľad dnešných rezervácií v tejto pobočke.
                        </p>
                    </div>

                    <Button
                        type="button"
                        label="Otvoriť kalendár"
                        @click="goToCalendar"
                    />
                </div>

                <div
                    v-if="todayAgenda.length"
                    class="mt-5 divide-y divide-soft"
                >
                    <article
                        v-for="item in todayAgenda"
                        :key="item.id"
                        class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex gap-3">
                            <p class="text-normal font-semibold text-dark">
                                {{ item.time }}
                            </p>

                            <p class="text-normal font-semibold text-dark">
                                {{ item.patient_name }}
                            </p>

                            <p class="text-normal text-accent">
                                {{ item.service_name || 'Bez služby' }}
                            </p>
                        </div>

                        <Tag>
                            {{ item.status_label }}
                        </Tag>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-5 rounded-md bg-soft p-5 text-center"
                >
                    <p class="text-normal font-semibold text-dark">
                        Dnes nie sú naplánované žiadne rezervácie.
                    </p>
                    <p class="mt-2 text-normal text-accent">
                        Agenda sa tu zobrazí automaticky, keď bude na dnešný deň vytvorená rezervácia.
                    </p>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>