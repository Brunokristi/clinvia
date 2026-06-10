<script setup>
import TableCard from '@/Components/Tables/TableCard.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import { useBranchBroadcasting } from '@/Composables/useBranchBroadcasting';
import { useBranchInboxBroadcasting } from '@/Composables/useBranchInboxBroadcasting';

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

const reloadCalendarData = (event = {}) => {
    if (event.action === 'appointment_request_created') {
        router.reload({
            only: [
                'pendingAppointmentRequestsCount',
            ],
            preserveState: true,
            preserveScroll: true,
        });

        return;
    }

    router.reload({
        only: [
            'todayBookingsCount',
            'pendingAppointmentRequestsCount',
            'todayAgenda',
        ],
        preserveState: true,
        preserveScroll: true,
    });
};

const reloadInboxData = () => {
    router.reload({
        only: [
            'unreadMessagesCount',
        ],
        preserveState: true,
        preserveScroll: true,
    });
};

useBranchBroadcasting(props.branch.id, reloadCalendarData);
useBranchInboxBroadcasting(props.branch.id, reloadInboxData);

const goToAgenda = () => {
    router.visit(route('branches.booking.agenda.page', {
        branch: props.branch.id,
    }));
};

const goToInbox = () => {
    router.visit(route('branches.inbox.index', {
        branch: props.branch.id,
    }));
};

const overviewCards = computed(() => [
    {
        title: 'Dnešné rezervácie',
        value: props.todayBookingsCount,
        description: 'Počet rezervácií naplánovaných na dnešný deň.',
        onClick: goToAgenda,
    },
    {
        title: 'Čakajúce žiadosti',
        value: props.pendingAppointmentRequestsCount,
        description: 'Žiadosti o rezerváciu, ktoré je potrebné spracovať.',
        onClick: goToAgenda,
    },
    {
        title: 'Nové správy',
        value: props.unreadMessagesCount,
        description: 'Nové správy v schránke, ktoré vyžadujú vašu pozornosť.',
        onClick: goToInbox,
    },
]);

const agendaColumns = computed(() => [
    {
        field: 'time',
        header: 'Čas',
        sortable: false,
        style: 'width: 140px',
    },
    {
        field: 'patient_name',
        header: 'Pacient',
        sortable: false,
    },
    {
        field: 'service_name',
        header: 'Služba',
        sortable: false,
        emptyValue: 'Bez služby',
    },
    {
        field: 'status_label',
        header: 'Stav',
        sortable: false,
        style: 'width: 160px',
    },
]);
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-1 xl:grid-cols-3">
                <button
                    v-for="card in overviewCards"
                    :key="card.title"
                    type="button"
                    class="rounded-md bg-soft p-5 text-left text-accent"
                    @click="card.onClick"
                >
                    <p class="text-normal font-semibold text-accent">
                        {{ card.title }}
                    </p>

                    <p class="mt-4 text-heading font-semibold text-dark">
                        {{ card.value }}
                    </p>

                    <p class="mt-3 text-normal leading-6 text-muted">
                        {{ card.description }}
                    </p>
                </button>
            </section>

            <TableCard
                title="Dnešná agenda"
                description="Prehľad dnešných rezervácií v tejto pobočke."
                :rows="todayAgenda"
                :columns="agendaColumns"
                :show-search="false"
                :paginator="false"
                table-style="min-width: 44rem"
                empty-message="Dnes nie sú naplánované žiadne rezervácie."
            >
                <template #actions>
                    <Button
                        type="button"
                        label="Otvoriť kalendár"
                        @click="goToAgenda"
                    />
                </template>

                <template #cell-time="{ row }">
                    <span class="text-normal font-semibold text-dark">
                        {{ row.time }}
                    </span>
                </template>

                <template #cell-patient_name="{ row }">
                    <span class="text-normal font-semibold text-dark">
                        {{ row.patient_name }}
                    </span>
                </template>

                <template #cell-service_name="{ row }">
                    <span class="text-normal text-accent">
                        {{ row.service_name || 'Bez služby' }}
                    </span>
                </template>

                <template #cell-status_label="{ row }">
                    <Tag>
                        {{ row.status_label }}
                    </Tag>
                </template>
            </TableCard>
        </div>
    </AdminLayout>
</template>