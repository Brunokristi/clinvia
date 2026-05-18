<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    branch: Object,
    companies: Array,
    availableUsers: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    company_id: props.branch.company_id,
    name: props.branch.name ?? '',
    slug: props.branch.slug ?? '',
    type: props.branch.type ?? '',
    description: props.branch.description ?? '',
    address_line_1: props.branch.address_line_1 ?? '',
    address_line_2: props.branch.address_line_2 ?? '',
    city: props.branch.city ?? '',
    postal_code: props.branch.postal_code ?? '',
    country: props.branch.country ?? 'Slovensko',
    latitude: props.branch.latitude ? Number(props.branch.latitude) : null,
    longitude: props.branch.longitude ? Number(props.branch.longitude) : null,
    website: props.branch.website ?? '',
    is_active: Boolean(props.branch.is_active),
    sort_order: props.branch.sort_order ?? 0,
});

const branchTypes = [
    { label: 'Ambulancia', value: 'ambulance' },
    { label: 'Centrum', value: 'center' },
    { label: 'Kancelária', value: 'office' },
    { label: 'Iné', value: 'other' },
];

const submit = () => {
    form.put(route('branches.update', props.branch.id), {
        preserveScroll: true,
    });
};

/**
 * Contacts
 */
const contactForm = useForm({
    type: 'phone',
    label: '',
    value: '',
    is_primary: false,
    sort_order: 0,
});

const contactTypes = [
    { label: 'Telefón', value: 'phone' },
    { label: 'Email', value: 'email' },
    { label: 'Web', value: 'website' },
    { label: 'Facebook', value: 'facebook' },
    { label: 'Instagram', value: 'instagram' },
    { label: 'Telefón na objednávanie', value: 'booking_phone' },
    { label: 'Fakturačný email', value: 'billing_email' },
    { label: 'Iné', value: 'other' },
];

const contactTypeLabel = (type) => {
    return contactTypes.find((item) => item.value === type)?.label ?? type;
};

const addContact = () => {
    contactForm.post(route('branches.contacts.store', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            contactForm.reset();
            contactForm.type = 'phone';
            contactForm.is_primary = false;
            contactForm.sort_order = 0;
        },
    });
};

const deleteContact = (contact) => {
    if (! confirm(`Naozaj chceš odstrániť kontakt ${contact.value}?`)) {
        return;
    }

    router.delete(route('branches.contacts.destroy', [props.branch.id, contact.id]), {
        preserveScroll: true,
    });
};

/**
 * Opening hours
 */
const dayNames = [
    { value: 1, label: 'Pondelok' },
    { value: 2, label: 'Utorok' },
    { value: 3, label: 'Streda' },
    { value: 4, label: 'Štvrtok' },
    { value: 5, label: 'Piatok' },
    { value: 6, label: 'Sobota' },
    { value: 7, label: 'Nedeľa' },
];

const createDefaultOpeningHours = () => {
    return dayNames.map((day) => {
        const existingDay = props.branch.opening_hours?.find(
            (item) => item.day_of_week === day.value
        );

        if (existingDay) {
            return {
                day_of_week: existingDay.day_of_week,
                is_closed: Boolean(existingDay.is_closed),
                note: existingDay.note ?? '',
                sort_order: existingDay.sort_order ?? day.value,
                intervals: existingDay.intervals?.map((interval, index) => ({
                    opens_at: interval.opens_at?.slice(0, 5) ?? '08:00',
                    closes_at: interval.closes_at?.slice(0, 5) ?? '16:00',
                    sort_order: interval.sort_order ?? index,
                })) ?? [],
            };
        }

        return {
            day_of_week: day.value,
            is_closed: day.value >= 6,
            note: '',
            sort_order: day.value,
            intervals: day.value >= 6
                ? []
                : [
                    {
                        opens_at: '08:00',
                        closes_at: '16:00',
                        sort_order: 0,
                    },
                ],
        };
    });
};

const openingHoursForm = useForm({
    opening_hours: createDefaultOpeningHours(),
});

const saveOpeningHours = () => {
    openingHoursForm.put(route('branches.opening-hours.update', props.branch.id), {
        preserveScroll: true,
    });
};

const addInterval = (day) => {
    day.is_closed = false;

    day.intervals.push({
        opens_at: '08:00',
        closes_at: '16:00',
        sort_order: day.intervals.length,
    });
};

const removeInterval = (day, intervalIndex) => {
    day.intervals.splice(intervalIndex, 1);

    day.intervals = day.intervals.map((interval, index) => ({
        ...interval,
        sort_order: index,
    }));
};

const dayLabel = (dayOfWeek) => {
    return dayNames.find((day) => day.value === dayOfWeek)?.label ?? dayOfWeek;
};

/**
 * Branch users
 */
const branchUserForm = useForm({
    user_id: null,
    role: 'branch_editor',
    is_active: true,
});

const branchUserRoles = [
    { label: 'Branch admin', value: 'branch_admin' },
    { label: 'Branch editor', value: 'branch_editor' },
    { label: 'Viewer', value: 'viewer' },
];

const branchUserRoleLabel = (role) => {
    return branchUserRoles.find((item) => item.value === role)?.label ?? role;
};

const attachBranchUser = () => {
    branchUserForm.post(route('branches.users.store', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            branchUserForm.reset();
            branchUserForm.role = 'branch_editor';
            branchUserForm.is_active = true;
        },
    });
};

const detachBranchUser = (user) => {
    if (! confirm(`Odstrániť používateľa ${user.name} z tejto pobočky?`)) {
        return;
    }

    router.delete(route('branches.users.destroy', [props.branch.id, user.id]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Upraviť pobočku
        </h1>

        <form class="max-w-4xl space-y-6" @submit.prevent="submit">
            <div class="rounded-lg border bg-white p-5">
                <h2 class="mb-4 text-lg font-semibold">
                    Základné údaje
                </h2>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Firma
                        </label>

                        <Select
                            v-model="form.company_id"
                            :options="companies"
                            optionLabel="name"
                            optionValue="id"
                            class="w-full"
                        />

                        <p v-if="form.errors.company_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.company_id }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Typ pobočky
                        </label>

                        <Select
                            v-model="form.type"
                            :options="branchTypes"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Vyber typ"
                            class="w-full"
                        />

                        <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Názov
                        </label>

                        <InputText v-model="form.name" class="w-full" />

                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Slug
                        </label>

                        <InputText v-model="form.slug" class="w-full" />

                        <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">
                            {{ form.errors.slug }}
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium">
                        Popis
                    </label>

                    <Textarea v-model="form.description" class="w-full" rows="5" />

                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                        {{ form.errors.description }}
                    </p>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-5">
                <h2 class="mb-4 text-lg font-semibold">
                    Adresa
                </h2>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Adresa 1
                        </label>

                        <InputText v-model="form.address_line_1" class="w-full" />

                        <p v-if="form.errors.address_line_1" class="mt-1 text-sm text-red-600">
                            {{ form.errors.address_line_1 }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Adresa 2
                        </label>

                        <InputText v-model="form.address_line_2" class="w-full" />

                        <p v-if="form.errors.address_line_2" class="mt-1 text-sm text-red-600">
                            {{ form.errors.address_line_2 }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Mesto
                        </label>

                        <InputText v-model="form.city" class="w-full" />

                        <p v-if="form.errors.city" class="mt-1 text-sm text-red-600">
                            {{ form.errors.city }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            PSČ
                        </label>

                        <InputText v-model="form.postal_code" class="w-full" />

                        <p v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">
                            {{ form.errors.postal_code }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Krajina
                        </label>

                        <InputText v-model="form.country" class="w-full" />

                        <p v-if="form.errors.country" class="mt-1 text-sm text-red-600">
                            {{ form.errors.country }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Web
                        </label>

                        <InputText v-model="form.website" class="w-full" />

                        <p v-if="form.errors.website" class="mt-1 text-sm text-red-600">
                            {{ form.errors.website }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Latitude
                        </label>

                        <InputNumber
                            v-model="form.latitude"
                            class="w-full"
                            inputClass="w-full"
                            :minFractionDigits="0"
                            :maxFractionDigits="7"
                        />

                        <p v-if="form.errors.latitude" class="mt-1 text-sm text-red-600">
                            {{ form.errors.latitude }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Longitude
                        </label>

                        <InputNumber
                            v-model="form.longitude"
                            class="w-full"
                            inputClass="w-full"
                            :minFractionDigits="0"
                            :maxFractionDigits="7"
                        />

                        <p v-if="form.errors.longitude" class="mt-1 text-sm text-red-600">
                            {{ form.errors.longitude }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-5">
                <h2 class="mb-4 text-lg font-semibold">
                    Nastavenia
                </h2>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Poradie
                        </label>

                        <InputNumber
                            v-model="form.sort_order"
                            class="w-full"
                            inputClass="w-full"
                        />

                        <p v-if="form.errors.sort_order" class="mt-1 text-sm text-red-600">
                            {{ form.errors.sort_order }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2 pt-7">
                        <Checkbox v-model="form.is_active" binary inputId="is_active_edit" />

                        <label for="is_active_edit">
                            Aktívna pobočka
                        </label>
                    </div>
                </div>
            </div>

            <Button
                type="submit"
                label="Uložiť základné údaje"
                icon="pi pi-save"
                :loading="form.processing"
            />
        </form>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <div class="mb-6">
                <h2 class="text-xl font-semibold">
                    Kontakty pobočky
                </h2>

                <p class="text-sm text-gray-500">
                    Tu nastavíš telefóny, emaily, sociálne siete alebo kontakty na objednávanie.
                </p>
            </div>

            <form class="mb-8 grid gap-5 md:grid-cols-5" @submit.prevent="addContact">
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Typ
                    </label>

                    <Select
                        v-model="contactForm.type"
                        :options="contactTypes"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />

                    <p v-if="contactForm.errors.type" class="mt-1 text-sm text-red-600">
                        {{ contactForm.errors.type }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Názov
                    </label>

                    <InputText
                        v-model="contactForm.label"
                        class="w-full"
                        placeholder="napr. Objednávanie"
                    />

                    <p v-if="contactForm.errors.label" class="mt-1 text-sm text-red-600">
                        {{ contactForm.errors.label }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Hodnota
                    </label>

                    <InputText
                        v-model="contactForm.value"
                        class="w-full"
                        placeholder="+421... alebo email"
                    />

                    <p v-if="contactForm.errors.value" class="mt-1 text-sm text-red-600">
                        {{ contactForm.errors.value }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Poradie
                    </label>

                    <InputNumber
                        v-model="contactForm.sort_order"
                        class="w-full"
                        inputClass="w-full"
                    />

                    <p v-if="contactForm.errors.sort_order" class="mt-1 text-sm text-red-600">
                        {{ contactForm.errors.sort_order }}
                    </p>
                </div>

                <div class="flex items-center gap-2 pt-7">
                    <Checkbox
                        v-model="contactForm.is_primary"
                        binary
                        inputId="contact_is_primary"
                    />

                    <label for="contact_is_primary">
                        Primárny
                    </label>
                </div>

                <div class="md:col-span-5">
                    <Button
                        type="submit"
                        label="Pridať kontakt"
                        icon="pi pi-plus"
                        :loading="contactForm.processing"
                    />
                </div>
            </form>

            <DataTable :value="branch.contacts ?? []" tableStyle="min-width: 50rem">
                <Column header="Typ">
                    <template #body="{ data }">
                        {{ contactTypeLabel(data.type) }}
                    </template>
                </Column>

                <Column field="label" header="Názov" />
                <Column field="value" header="Hodnota" />

                <Column header="Primárny">
                    <template #body="{ data }">
                        {{ data.is_primary ? 'Áno' : 'Nie' }}
                    </template>
                </Column>

                <Column field="sort_order" header="Poradie" />

                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteContact(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <div class="mb-6">
                <h2 class="text-xl font-semibold">
                    Otváracie hodiny
                </h2>

                <p class="text-sm text-gray-500">
                    Nastav týždenný rozpis pobočky. Pre obednú prestávku pridaj viac intervalov v jednom dni.
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="saveOpeningHours">
                <div
                    v-for="(day, dayIndex) in openingHoursForm.opening_hours"
                    :key="day.day_of_week"
                    class="rounded-lg border p-4"
                >
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-semibold">
                                {{ dayLabel(day.day_of_week) }}
                            </h3>

                            <p
                                v-if="openingHoursForm.errors[`opening_hours.${dayIndex}.intervals`]"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ openingHoursForm.errors[`opening_hours.${dayIndex}.intervals`] }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-model="day.is_closed"
                                binary
                                :inputId="`closed_${day.day_of_week}`"
                            />

                            <label :for="`closed_${day.day_of_week}`">
                                Zatvorené
                            </label>
                        </div>
                    </div>

                    <div v-if="! day.is_closed" class="space-y-3">
                        <div
                            v-for="(interval, intervalIndex) in day.intervals"
                            :key="intervalIndex"
                            class="grid items-end gap-4 md:grid-cols-[1fr_1fr_auto]"
                        >
                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Od
                                </label>

                                <input
                                    v-model="interval.opens_at"
                                    type="time"
                                    class="w-full rounded-md border-gray-300"
                                />

                                <p
                                    v-if="openingHoursForm.errors[`opening_hours.${dayIndex}.intervals.${intervalIndex}.opens_at`]"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ openingHoursForm.errors[`opening_hours.${dayIndex}.intervals.${intervalIndex}.opens_at`] }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">
                                    Do
                                </label>

                                <input
                                    v-model="interval.closes_at"
                                    type="time"
                                    class="w-full rounded-md border-gray-300"
                                />

                                <p
                                    v-if="openingHoursForm.errors[`opening_hours.${dayIndex}.intervals.${intervalIndex}.closes_at`]"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ openingHoursForm.errors[`opening_hours.${dayIndex}.intervals.${intervalIndex}.closes_at`] }}
                                </p>
                            </div>

                            <Button
                                type="button"
                                label="Odstrániť"
                                severity="danger"
                                outlined
                                @click="removeInterval(day, intervalIndex)"
                            />
                        </div>

                        <Button
                            type="button"
                            label="Pridať interval"
                            icon="pi pi-plus"
                            outlined
                            @click="addInterval(day)"
                        />
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-medium">
                            Poznámka
                        </label>

                        <InputText
                            v-model="day.note"
                            class="w-full"
                            placeholder="napr. iba na objednanie"
                        />
                    </div>
                </div>

                <Button
                    type="submit"
                    label="Uložiť otváracie hodiny"
                    icon="pi pi-save"
                    :loading="openingHoursForm.processing"
                />
            </form>
        </section>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <div class="mb-6">
                <h2 class="text-xl font-semibold">
                    Používatelia pobočky
                </h2>

                <p class="text-sm text-gray-500">
                    Tu vieš priradiť používateľov, ktorí majú prístup iba k tejto pobočke.
                </p>
            </div>

            <form class="mb-8 grid gap-5 md:grid-cols-4" @submit.prevent="attachBranchUser">
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Používateľ
                    </label>

                    <Select
                        v-model="branchUserForm.user_id"
                        :options="availableUsers"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Vyber používateľa"
                        class="w-full"
                    />

                    <p v-if="branchUserForm.errors.user_id" class="mt-1 text-sm text-red-600">
                        {{ branchUserForm.errors.user_id }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Rola v pobočke
                    </label>

                    <Select
                        v-model="branchUserForm.role"
                        :options="branchUserRoles"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />

                    <p v-if="branchUserForm.errors.role" class="mt-1 text-sm text-red-600">
                        {{ branchUserForm.errors.role }}
                    </p>
                </div>

                <div class="flex items-center gap-2 pt-7">
                    <Checkbox
                        v-model="branchUserForm.is_active"
                        binary
                        inputId="branch_user_active"
                    />

                    <label for="branch_user_active">
                        Aktívny prístup
                    </label>
                </div>

                <div class="pt-6">
                    <Button
                        type="submit"
                        label="Priradiť"
                        icon="pi pi-plus"
                        :loading="branchUserForm.processing"
                    />
                </div>
            </form>

            <DataTable :value="branch.users ?? []" tableStyle="min-width: 50rem">
                <Column field="name" header="Meno" />
                <Column field="email" header="Email" />
                <Column field="global_role" header="Globálna rola" />

                <Column header="Rola v pobočke">
                    <template #body="{ data }">
                        {{ branchUserRoleLabel(data.pivot.role) }}
                    </template>
                </Column>

                <Column header="Aktívny prístup">
                    <template #body="{ data }">
                        {{ data.pivot.is_active ? 'Áno' : 'Nie' }}
                    </template>
                </Column>

                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Odobrať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="detachBranchUser(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>
    </AdminLayout>
</template>