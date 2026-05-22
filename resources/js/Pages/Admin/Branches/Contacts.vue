<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

const props = defineProps({
    branch: Object,
});

const contactForm = useForm({
    type: 'phone',
    label: 'Hlavný telefón',
    custom_label: '',
    value: '',
    is_primary: false,
    sort_order: 0,
});

const contactTypes = [
    {
        label: 'Telefón',
        value: 'phone',
        icon: 'pi pi-phone',
        valueLabel: 'Telefónne číslo',
        placeholder: '+421 900 000 000',
        inputType: 'tel',
    },
    {
        label: 'Email',
        value: 'email',
        icon: 'pi pi-envelope',
        valueLabel: 'Emailová adresa',
        placeholder: 'info@firma.sk',
        inputType: 'email',
    },
    {
        label: 'Web',
        value: 'website',
        icon: 'pi pi-globe',
        valueLabel: 'Webová stránka',
        placeholder: 'https://www.firma.sk',
        inputType: 'url',
    },
    {
        label: 'Facebook',
        value: 'facebook',
        icon: 'pi pi-facebook',
        valueLabel: 'Facebook odkaz',
        placeholder: 'https://facebook.com/...',
        inputType: 'url',
    },
    {
        label: 'Instagram',
        value: 'instagram',
        icon: 'pi pi-instagram',
        valueLabel: 'Instagram odkaz',
        placeholder: 'https://instagram.com/...',
        inputType: 'url',
    },
    {
        label: 'Telefón na objednávanie',
        value: 'booking_phone',
        icon: 'pi pi-calendar-plus',
        valueLabel: 'Telefónne číslo',
        placeholder: '+421 900 000 000',
        inputType: 'tel',
    },
    {
        label: 'Fakturačný email',
        value: 'billing_email',
        icon: 'pi pi-receipt',
        valueLabel: 'Fakturačný email',
        placeholder: 'fakturacia@firma.sk',
        inputType: 'email',
    },
    {
        label: 'Iné',
        value: 'other',
        icon: 'pi pi-link',
        valueLabel: 'Hodnota kontaktu',
        placeholder: 'Zadajte kontakt',
        inputType: 'text',
    },
];

const labelOptionsByType = {
    phone: [
        { label: 'Hlavný telefón', value: 'Hlavný telefón' },
        { label: 'Recepcia', value: 'Recepcia' },
        { label: 'Ambulancia', value: 'Ambulancia' },
        { label: 'Objednávanie', value: 'Objednávanie' },
        { label: 'Iné', value: 'other' },
    ],
    email: [
        { label: 'Hlavný email', value: 'Hlavný email' },
        { label: 'Recepcia', value: 'Recepcia' },
        { label: 'Objednávky', value: 'Objednávky' },
        { label: 'Podpora', value: 'Podpora' },
        { label: 'Iné', value: 'other' },
    ],
    website: [
        { label: 'Web stránka', value: 'Web stránka' },
        { label: 'Rezervačný systém', value: 'Rezervačný systém' },
        { label: 'Cenník', value: 'Cenník' },
        { label: 'Iné', value: 'other' },
    ],
    facebook: [
        { label: 'Facebook stránka', value: 'Facebook stránka' },
        { label: 'Facebook profil', value: 'Facebook profil' },
        { label: 'Iné', value: 'other' },
    ],
    instagram: [
        { label: 'Instagram profil', value: 'Instagram profil' },
        { label: 'Iné', value: 'other' },
    ],
    booking_phone: [
        { label: 'Objednávanie', value: 'Objednávanie' },
        { label: 'Recepcia', value: 'Recepcia' },
        { label: 'Iné', value: 'other' },
    ],
    billing_email: [
        { label: 'Fakturácia', value: 'Fakturácia' },
        { label: 'Účtovníctvo', value: 'Účtovníctvo' },
        { label: 'Iné', value: 'other' },
    ],
    other: [
        { label: 'WhatsApp', value: 'WhatsApp' },
        { label: 'LinkedIn', value: 'LinkedIn' },
        { label: 'YouTube', value: 'YouTube' },
        { label: 'TikTok', value: 'TikTok' },
        { label: 'Iné', value: 'other' },
    ],
};

const selectedType = computed(() => {
    return contactTypes.find((item) => item.value === contactForm.type) ?? contactTypes[0];
});

const labelOptions = computed(() => {
    return labelOptionsByType[contactForm.type] ?? labelOptionsByType.other;
});

const isCustomLabel = computed(() => contactForm.label === 'other');

const finalLabel = computed(() => {
    if (isCustomLabel.value) {
        return contactForm.custom_label.trim();
    }

    return contactForm.label;
});

const valueHelpText = computed(() => {
    if (['phone', 'booking_phone'].includes(contactForm.type)) {
        return 'Použite medzinárodný formát, napríklad +421 900 000 000.';
    }

    if (['email', 'billing_email'].includes(contactForm.type)) {
        return 'Zadajte platnú emailovú adresu.';
    }

    if (['website', 'facebook', 'instagram'].includes(contactForm.type)) {
        return 'Vložte celý odkaz vrátane https://.';
    }

    return 'Zadajte hodnotu kontaktu podľa zvoleného názvu.';
});

const canSubmit = computed(() => {
    return Boolean(contactForm.type)
        && Boolean(finalLabel.value)
        && Boolean(contactForm.value.trim());
});

const contactTypeLabel = (type) => {
    return contactTypes.find((item) => item.value === type)?.label ?? type;
};

const contactTypeIcon = (type) => {
    return contactTypes.find((item) => item.value === type)?.icon ?? 'pi pi-link';
};

watch(() => contactForm.type, (newType) => {
    contactForm.value = '';
    contactForm.custom_label = '';
    contactForm.label = labelOptionsByType[newType]?.[0]?.value ?? '';
});

const addContact = () => {
    contactForm.label = finalLabel.value;
    contactForm.is_primary = false;
    contactForm.sort_order = 0;

    contactForm.post(route('branches.contacts.store', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            contactForm.reset();
            contactForm.type = 'phone';
            contactForm.label = 'Hlavný telefón';
            contactForm.custom_label = '';
            contactForm.value = '';
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
</script>

<template>
    <AdminLayout>
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                    Pobočka
                </p>

                <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                    Kontakty pobočky
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Pridajte telefóny, emaily, web a sociálne siete. Názov kontaktu vyberte z možností alebo zvoľte vlastný.
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
                        Pridať kontakt
                    </h2>

                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Najprv vyberte typ kontaktu. Pole hodnota sa prispôsobí podľa typu.
                    </p>
                </div>

                <form class="grid gap-5 lg:grid-cols-3" @submit.prevent="addContact">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Typ kontaktu
                        </label>

                        <Select
                            v-model="contactForm.type"
                            :options="contactTypes"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        >
                            <template #value="{ value }">
                                <div class="flex items-center gap-2">
                                    <i :class="contactTypeIcon(value)" class="text-sm text-slate-500" />
                                    <span>{{ contactTypeLabel(value) }}</span>
                                </div>
                            </template>

                            <template #option="{ option }">
                                <div class="flex items-center gap-2">
                                    <i :class="option.icon" class="text-sm text-slate-500" />
                                    <span>{{ option.label }}</span>
                                </div>
                            </template>
                        </Select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Názov kontaktu
                        </label>

                        <Select
                            v-model="contactForm.label"
                            :options="labelOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>

                    <div v-if="isCustomLabel">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Vlastný názov
                        </label>

                        <InputText
                            v-model="contactForm.custom_label"
                            class="w-full"
                            placeholder="Napr. WhatsApp, LinkedIn..."
                        />
                    </div>

                    <div :class="isCustomLabel ? '' : 'lg:col-span-1'">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            {{ selectedType.valueLabel }}
                        </label>

                        <InputText
                            v-model="contactForm.value"
                            :type="selectedType.inputType"
                            class="w-full"
                            :placeholder="selectedType.placeholder"
                        />

                        <p class="mt-1 text-xs text-slate-500">
                            {{ valueHelpText }}
                        </p>

                        <p
                            v-if="contactForm.errors.value"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ contactForm.errors.value }}
                        </p>
                    </div>

                    <div class="lg:col-span-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">
                            Náhľad
                        </p>

                        <div class="mt-3 flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-slate-600 shadow-sm">
                                <i :class="selectedType.icon" />
                            </div>

                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ finalLabel || 'Názov kontaktu' }}
                                </p>

                                <p class="mt-1 truncate text-sm text-slate-500">
                                    {{ contactForm.value || selectedType.placeholder }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end lg:col-span-3">
                        <Button
                            type="submit"
                            label="Pridať kontakt"
                            icon="pi pi-plus"
                            :loading="contactForm.processing"
                            :disabled="!canSubmit"
                        />
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">
                                Existujúce kontakty
                            </h2>

                            <p class="mt-1 text-sm text-slate-600">
                                Zoznam kontaktov priradených k tejto pobočke.
                            </p>
                        </div>

                        <Tag
                            :value="`${branch.contacts?.length ?? 0} kontaktov`"
                            severity="secondary"
                        />
                    </div>
                </div>

                <DataTable
                    :value="branch.contacts ?? []"
                    tableStyle="min-width: 48rem"
                    class="rounded-b-2xl"
                    emptyMessage="Táto pobočka zatiaľ nemá žiadne kontakty."
                >
                    <Column header="Typ">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                    <i :class="contactTypeIcon(data.type)" class="text-sm" />
                                </div>

                                <span class="text-sm font-medium text-slate-800">
                                    {{ contactTypeLabel(data.type) }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Názov">
                        <template #body="{ data }">
                            <span class="text-sm text-slate-700">
                                {{ data.label || 'Bez názvu' }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Hodnota">
                        <template #body="{ data }">
                            <span class="text-sm font-medium text-slate-900">
                                {{ data.value }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Akcie">
                        <template #body="{ data }">
                            <Button
                                label="Odstrániť"
                                size="small"
                                severity="danger"
                                outlined
                                icon="pi pi-trash"
                                @click="deleteContact(data)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </section>
        </div>
    </AdminLayout>
</template>