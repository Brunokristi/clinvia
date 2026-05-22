<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import FileUpload from 'primevue/fileupload';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

const props = defineProps({
    branch: Object,
});

const employeeForm = useForm({
    create_new: true,
    first_name: '',
    last_name: '',
    title_before: '',
    title_after: '',
    position: '',
    bio: '',
    email: '',
    phone: '',
    photo: null,
    role: '',
    sort_order: 0,
});

const employeeDisplayName = (employee) => {
    return [
        employee.title_before,
        employee.first_name,
        employee.last_name,
        employee.title_after,
    ].filter(Boolean).join(' ');
};

const addEmployee = () => {
    employeeForm.sort_order = 0;

    employeeForm.post(route('branches.employees.store', props.branch.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            employeeForm.reset();
            employeeForm.create_new = true;
            employeeForm.sort_order = 0;
        },
    });
};

const removeEmployee = (employee) => {
    if (!confirm(`Odstrániť zamestnanca ${employeeDisplayName(employee)} z tejto pobočky?`)) {
        return;
    }

    router.delete(route('branches.employees.destroy', [props.branch.id, employee.id]), {
        preserveScroll: true,
    });
};

const handleEmployeePhoto = (event) => {
    employeeForm.photo = event.files?.[0] ?? null;
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
                    Zamestnanci pobočky
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Vytvorte profil zamestnanca a priraďte ho k tejto pobočke.
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
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Pridať zamestnanca
                    </h2>

                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Vyplňte základné údaje zamestnanca. Meno, priezvisko a pozícia sú povinné.
                    </p>
                </div>

                <form class="space-y-6" @submit.prevent="addEmployee">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">
                            Osobné údaje
                        </h3>

                        <p class="mt-1 text-sm text-slate-600">
                            Tituly, meno a pracovná pozícia zamestnanca.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Titul pred menom
                            </label>

                            <InputText
                                v-model="employeeForm.title_before"
                                class="w-full"
                                placeholder="Mgr., PhDr., MUDr."
                            />

                            <p
                                v-if="employeeForm.errors.title_before"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.title_before }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Titul za menom
                            </label>

                            <InputText
                                v-model="employeeForm.title_after"
                                class="w-full"
                                placeholder="PhD., MBA"
                            />

                            <p
                                v-if="employeeForm.errors.title_after"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.title_after }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Meno <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="employeeForm.first_name"
                                class="w-full"
                                placeholder="Ján"
                            />

                            <p
                                v-if="employeeForm.errors.first_name"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.first_name }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Priezvisko <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="employeeForm.last_name"
                                class="w-full"
                                placeholder="Novák"
                            />

                            <p
                                v-if="employeeForm.errors.last_name"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.last_name }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Pozícia <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="employeeForm.position"
                                class="w-full"
                                placeholder="Klinický psychológ"
                            />

                            <p
                                v-if="employeeForm.errors.position"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.position }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Rola v pobočke
                            </label>

                            <InputText
                                v-model="employeeForm.role"
                                class="w-full"
                                placeholder="Napr. vedúci pobočky"
                            />

                            <p
                                v-if="employeeForm.errors.role"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.role }}
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-6">
                        <h3 class="text-base font-semibold text-slate-900">
                            Kontakt a profil
                        </h3>

                        <p class="mt-1 text-sm text-slate-600">
                            Nepovinné údaje, ktoré môžu byť použité v profile zamestnanca.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Email
                            </label>

                            <InputText
                                v-model="employeeForm.email"
                                class="w-full"
                                placeholder="meno@firma.sk"
                            />

                            <p
                                v-if="employeeForm.errors.email"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Telefón
                            </label>

                            <InputText
                                v-model="employeeForm.phone"
                                class="w-full"
                                placeholder="+421..."
                            />

                            <p
                                v-if="employeeForm.errors.phone"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.phone }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Bio
                            </label>

                            <Textarea
                                v-model="employeeForm.bio"
                                class="w-full"
                                rows="4"
                                placeholder="Krátky popis, špecializácia alebo prax..."
                            />

                            <p
                                v-if="employeeForm.errors.bio"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.bio }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Fotografia
                            </label>

                            <FileUpload
                                mode="basic"
                                name="photo"
                                accept="image/*"
                                chooseLabel="Vybrať fotografiu"
                                customUpload
                                auto
                                @select="handleEmployeePhoto"
                            />

                            <p
                                v-if="employeeForm.photo"
                                class="mt-2 text-sm text-slate-500"
                            >
                                Vybraný súbor: {{ employeeForm.photo.name }}
                            </p>

                            <p
                                v-if="employeeForm.errors.photo"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ employeeForm.errors.photo }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-200 pt-5">
                        <Button
                            type="submit"
                            label="Pridať zamestnanca"
                            icon="pi pi-plus"
                            :loading="employeeForm.processing"
                        />
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">
                                Existujúci zamestnanci
                            </h2>

                            <p class="mt-1 text-sm text-slate-600">
                                Zamestnanci aktuálne priradení k tejto pobočke.
                            </p>
                        </div>

                        <Tag
                            :value="`${branch.employees?.length ?? 0} zamestnancov`"
                            severity="secondary"
                        />
                    </div>
                </div>

                <DataTable
                    :value="branch.employees ?? []"
                    tableStyle="min-width: 56rem"
                    emptyMessage="Táto pobočka zatiaľ nemá priradených zamestnancov."
                >
                    <Column header="Zamestnanec">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-slate-700">
                                    {{ data.first_name?.charAt(0) }}{{ data.last_name?.charAt(0) }}
                                </div>

                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ employeeDisplayName(data) }}
                                    </p>

                                    <p class="text-xs text-slate-500">
                                        {{ data.position || 'Pozícia nie je zadaná' }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Rola v pobočke">
                        <template #body="{ data }">
                            <span class="text-sm text-slate-700">
                                {{ data.pivot?.role || '—' }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Kontakt">
                        <template #body="{ data }">
                            <div class="space-y-1 text-sm text-slate-700">
                                <p v-if="data.email">
                                    {{ data.email }}
                                </p>

                                <p v-if="data.phone">
                                    {{ data.phone }}
                                </p>

                                <p
                                    v-if="!data.email && !data.phone"
                                    class="text-slate-400"
                                >
                                    —
                                </p>
                            </div>
                        </template>
                    </Column>

                    <Column header="Stav">
                        <template #body="{ data }">
                            <Tag
                                :value="data.is_active ? 'Aktívny' : 'Neaktívny'"
                                :severity="data.is_active ? 'success' : 'secondary'"
                            />
                        </template>
                    </Column>

                    <Column header="Akcie">
                        <template #body="{ data }">
                            <Button
                                label="Odobrať"
                                size="small"
                                severity="danger"
                                outlined
                                icon="pi pi-trash"
                                @click="removeEmployee(data)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </section>
        </div>
    </AdminLayout>
</template>