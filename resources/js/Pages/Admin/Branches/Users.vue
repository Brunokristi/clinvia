<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import InvitationFormSection from '@/Components/Invitations/InvitationFormSection.vue';

const props = defineProps({
    branch: Object,
});

const inviteForm = useForm({
    invite_email: '',
});

const userDisplayName = (user) => {
    return [user.first_name, user.last_name].filter(Boolean).join(' ') || user.name || '—';
};

const branchAdmins = computed(() => {
    return (props.branch.users ?? [])
        .filter((user) => user.pivot?.role === 'branch_admin')
        .map((user) => ({
            ...user,
            source: 'branch',
        }));
});

const companyAdmins = computed(() => {
    return (props.branch.company?.users ?? [])
        .filter((user) => user.global_role === 'admin')
        .map((user) => ({
            ...user,
            source: 'company',
        }));
});

const adminUsers = computed(() => {
    const seen = new Set();

    return [...branchAdmins.value, ...companyAdmins.value].filter((user) => {
        if (seen.has(user.id)) {
            return false;
        }

        seen.add(user.id);
        return true;
    });
});

const inviteBranchAdmin = () => {
    inviteForm.post(route('branches.users.store', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset('invite_email');
        },
    });
};

const detachBranchUser = (user) => {
    if (!confirm(`Odstrániť používateľa ${userDisplayName(user)} z tejto pobočky?`)) {
        return;
    }

    router.delete(route('branches.users.destroy', [props.branch.id, user.id]), {
        preserveScroll: true,
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

        <div class="space-y-6">
            <form @submit.prevent="inviteBranchAdmin">
                <InvitationFormSection
                    :form="inviteForm"
                    title="Pozvať branch admina"
                    description="Pozvánka ide na email a po prijatí sa používateľ priradí s rolou branch_admin."
                    input-label="Email branch admina"
                    submit-label="Poslať pozvánku"
                    :loading="inviteForm.processing"
                />
            </form>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">
                                Admini pobočky a spoločnosti
                            </h2>

                            <p class="mt-1 text-sm text-slate-600">
                                Tu vidíš branch adminov aj company adminov, ktorí majú prístup cez spoločnosť.
                            </p>
                        </div>

                        <Tag
                            :value="`${adminUsers.length} adminov`"
                            severity="secondary"
                        />
                    </div>
                </div>

                <DataTable
                    :value="adminUsers"
                    tableStyle="min-width: 56rem"
                    emptyMessage="Táto pobočka zatiaľ nemá žiadnych adminov."
                >
                    <Column header="Používateľ">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-700">
                                    {{ data.first_name?.charAt(0) }}{{ data.last_name?.charAt(0) }}
                                </div>

                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ userDisplayName(data) }}
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
                                :value="data.source === 'company' ? 'Company admin' : 'Branch admin'"
                                :severity="data.source === 'company' ? 'info' : 'success'"
                            />
                        </template>
                    </Column>

                    <Column header="Rola">
                        <template #body="{ data }">
                            <Tag
                                :value="data.source === 'company' ? 'Admin' : 'Branch admin'"
                                :severity="data.source === 'company' ? 'info' : 'success'"
                            />
                        </template>
                    </Column>

                    <Column header="Prístup">
                        <template #body="{ data }">
                            <Tag
                                :value="data.pivot?.is_active ? 'Aktívny' : 'Neaktívny'"
                                :severity="data.pivot?.is_active ? 'success' : 'secondary'"
                            />
                        </template>
                    </Column>

                    <Column header="Akcie">
                        <template #body="{ data }">
                            <Button
                                v-if="data.source === 'branch'"
                                label="Odobrať"
                                size="small"
                                severity="danger"
                                outlined
                                icon="pi pi-trash"
                                @click="detachBranchUser(data)"
                            />

                            <Tag
                                v-else
                                value="Spravuje superadmin"
                                severity="warning"
                            />
                        </template>
                    </Column>
                </DataTable>
            </section>
        </div>
    </AdminLayout>
</template>
