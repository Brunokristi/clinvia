<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputMask from 'primevue/inputmask';
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

const selectedPhoneCountryCode = ref('SK');

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const phoneCountries = [
    {
        label: 'Slovakia',
        value: 'SK',
        dialCode: '+421',
        flag: '🇸🇰',
        mask: '999 999 999',
        placeholder: '900 123 456',
    },
    {
        label: 'Czech Republic',
        value: 'CZ',
        dialCode: '+420',
        flag: '🇨🇿',
        mask: '999 999 999',
        placeholder: '777 123 456',
    },
    {
        label: 'Austria',
        value: 'AT',
        dialCode: '+43',
        flag: '🇦🇹',
        mask: '999 999 9999',
        placeholder: '660 123 4567',
    },
    {
        label: 'Hungary',
        value: 'HU',
        dialCode: '+36',
        flag: '🇭🇺',
        mask: '99 999 9999',
        placeholder: '30 123 4567',
    },
    {
        label: 'Poland',
        value: 'PL',
        dialCode: '+48',
        flag: '🇵🇱',
        mask: '999 999 999',
        placeholder: '500 123 456',
    },
];

const contactTypes = [
    {
        label: 'Telefón',
        value: 'phone',
        icon: 'pi pi-phone',
        valueLabel: 'Telefónne číslo',
        placeholder: '900 123 456',
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
        placeholder: '900 123 456',
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

const selectedPhoneCountry = computed(() => {
    return phoneCountries.find((item) => item.value === selectedPhoneCountryCode.value) ?? phoneCountries[0];
});

const labelOptions = computed(() => {
    return labelOptionsByType[contactForm.type] ?? labelOptionsByType.other;
});

const isPhoneType = computed(() => {
    return ['phone', 'booking_phone'].includes(contactForm.type);
});

const isCustomLabel = computed(() => contactForm.label === 'other');

const finalLabel = computed(() => {
    if (isCustomLabel.value) {
        return contactForm.custom_label.trim();
    }

    return contactForm.label;
});

const formattedPhonePreview = computed(() => {
    if (!isPhoneType.value || !contactForm.value) {
        return '';
    }

    return `${selectedPhoneCountry.value.dialCode} ${contactForm.value}`.trim();
});

const previewValue = computed(() => {
    if (isPhoneType.value) {
        return formattedPhonePreview.value || selectedType.value.placeholder;
    }

    return contactForm.value || selectedType.value.placeholder;
});

const valueHelpText = computed(() => {
    if (isPhoneType.value) {
        return 'Vyberte predvoľbu krajiny a zadajte iba čísla. Formát sa doplní automaticky.';
    }

    if (['email', 'billing_email'].includes(contactForm.type)) {
        return 'Zadajte platnú emailovú adresu.';
    }

    if (['website', 'facebook', 'instagram'].includes(contactForm.type)) {
        return 'Vložte celý odkaz vrátane https://. Ak ho nezadáte, doplní sa automaticky.';
    }

    return 'Zadajte hodnotu kontaktu podľa zvoleného názvu.';
});

const canSubmit = computed(() => {
    return Boolean(contactForm.type)
        && Boolean(finalLabel.value)
        && Boolean(String(contactForm.value || '').trim());
});

const contactTypeLabel = (type) => {
    return contactTypes.find((item) => item.value === type)?.label ?? type;
};

const contactTypeIcon = (type) => {
    return contactTypes.find((item) => item.value === type)?.icon ?? 'pi pi-link';
};

const normalizeEmail = (value) => {
    return String(value || '')
        .trim()
        .toLowerCase();
};

const normalizeUrl = (value) => {
    const cleanValue = String(value || '').trim();

    if (!cleanValue) {
        return '';
    }

    if (/^https?:\/\//i.test(cleanValue)) {
        return cleanValue;
    }

    return `https://${cleanValue}`;
};

const normalizeContactValueBeforeSubmit = () => {
    if (isPhoneType.value) {
        contactForm.value = `${selectedPhoneCountry.value.dialCode} ${contactForm.value}`.trim();
        return;
    }

    if (['email', 'billing_email'].includes(contactForm.type)) {
        contactForm.value = normalizeEmail(contactForm.value);
        return;
    }

    if (['website', 'facebook', 'instagram'].includes(contactForm.type)) {
        contactForm.value = normalizeUrl(contactForm.value);
    }
};

const resetContactForm = () => {
    contactForm.reset();

    contactForm.type = 'phone';
    contactForm.label = 'Hlavný telefón';
    contactForm.custom_label = '';
    contactForm.value = '';
    contactForm.is_primary = false;
    contactForm.sort_order = 0;

    selectedPhoneCountryCode.value = 'SK';
};

watch(() => contactForm.type, (newType) => {
    contactForm.value = '';
    contactForm.custom_label = '';
    contactForm.label = labelOptionsByType[newType]?.[0]?.value ?? '';

    if (['phone', 'booking_phone'].includes(newType)) {
        selectedPhoneCountryCode.value = 'SK';
    }
});

const addContact = () => {
    normalizeContactValueBeforeSubmit();

    contactForm.label = finalLabel.value;
    contactForm.is_primary = false;
    contactForm.sort_order = 0;

    contactForm.post(route('branches.contacts.store', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetContactForm();
        },
    });
};

const deleteContact = (contact) => {
    openDialog({
        title: 'Odstrániť kontakt',
        message: `Naozaj odstrániť kontakt ${contact.value}?`,
        confirmLabel: 'Zmazať',
        onConfirm: () => {
            router.delete(route('branches.contacts.destroy', [props.branch.id, contact.id]), {
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

                    <div :class="isCustomLabel ? 'lg:col-span-3' : 'lg:col-span-1'">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            {{ selectedType.valueLabel }}
                        </label>

                        <div
                            v-if="isPhoneType"
                            class="grid gap-3 sm:grid-cols-[4.5rem_1fr]"
                        >
                            <Select
                                v-model="selectedPhoneCountryCode"
                                :options="phoneCountries"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            >
                                <template #value="{ value }">
                                    <div class="flex items-center gap-2">
                                        <span>{{ selectedPhoneCountry.flag }}</span>
                                        <span class="font-medium">{{ selectedPhoneCountry.dialCode }}</span>
                                    </div>
                                </template>

                                <template #option="{ option }">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <span>{{ option.flag }}</span>
                                            <span>{{ option.label }}</span>
                                        </div>

                                        <span class="text-sm text-slate-500">
                                            {{ option.dialCode }}
                                        </span>
                                    </div>
                                </template>
                            </Select>

                            <InputMask
                                v-model="contactForm.value"
                                :mask="selectedPhoneCountry.mask"
                                :placeholder="selectedPhoneCountry.placeholder"
                                class="w-full"
                                inputmode="numeric"
                                slotChar=""
                            />
                        </div>

                        <InputText
                            v-else
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

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 lg:col-span-3">
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
                                    {{ previewValue }}
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