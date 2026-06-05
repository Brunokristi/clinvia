<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import PhoneInput from '@/Components/Forms/PhoneInput.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
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
const phoneFullValue = ref('');

const faqItems = ref(
    (props.branch.public_site?.faq_items ?? []).length
        ? (props.branch.public_site.faq_items ?? []).map((item) => ({
            question: item.question ?? '',
            answer: item.answer ?? '',
        }))
        : [
            {
                question: '',
                answer: '',
            },
        ],
);

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

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
        {
            label: 'Hlavný telefón',
            value: 'Hlavný telefón',
        },
        {
            label: 'Recepcia',
            value: 'Recepcia',
        },
        {
            label: 'Ambulancia',
            value: 'Ambulancia',
        },
        {
            label: 'Objednávanie',
            value: 'Objednávanie',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
    email: [
        {
            label: 'Hlavný email',
            value: 'Hlavný email',
        },
        {
            label: 'Recepcia',
            value: 'Recepcia',
        },
        {
            label: 'Objednávky',
            value: 'Objednávky',
        },
        {
            label: 'Podpora',
            value: 'Podpora',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
    website: [
        {
            label: 'Web stránka',
            value: 'Web stránka',
        },
        {
            label: 'Rezervačný systém',
            value: 'Rezervačný systém',
        },
        {
            label: 'Cenník',
            value: 'Cenník',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
    facebook: [
        {
            label: 'Facebook stránka',
            value: 'Facebook stránka',
        },
        {
            label: 'Facebook profil',
            value: 'Facebook profil',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
    instagram: [
        {
            label: 'Instagram profil',
            value: 'Instagram profil',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
    booking_phone: [
        {
            label: 'Objednávanie',
            value: 'Objednávanie',
        },
        {
            label: 'Recepcia',
            value: 'Recepcia',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
    billing_email: [
        {
            label: 'Fakturácia',
            value: 'Fakturácia',
        },
        {
            label: 'Účtovníctvo',
            value: 'Účtovníctvo',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
    other: [
        {
            label: 'WhatsApp',
            value: 'WhatsApp',
        },
        {
            label: 'LinkedIn',
            value: 'LinkedIn',
        },
        {
            label: 'YouTube',
            value: 'YouTube',
        },
        {
            label: 'TikTok',
            value: 'TikTok',
        },
        {
            label: 'Iné',
            value: 'other',
        },
    ],
};

const selectedType = computed(() => {
    return contactTypes.find((item) => item.value === contactForm.type) ?? contactTypes[0];
});

const labelOptions = computed(() => {
    return labelOptionsByType[contactForm.type] ?? labelOptionsByType.other;
});

const isPhoneType = computed(() => {
    return ['phone', 'booking_phone'].includes(contactForm.type);
});

const isCustomLabel = computed(() => {
    return contactForm.label === 'other';
});

const finalLabel = computed(() => {
    if (isCustomLabel.value) {
        return contactForm.custom_label.trim();
    }

    return contactForm.label;
});

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

const normalizedContactValue = computed(() => {
    if (isPhoneType.value) {
        return phoneFullValue.value.trim();
    }

    if (['email', 'billing_email'].includes(contactForm.type)) {
        return normalizeEmail(contactForm.value);
    }

    if (['website', 'facebook', 'instagram'].includes(contactForm.type)) {
        return normalizeUrl(contactForm.value);
    }

    return String(contactForm.value || '').trim();
});

const canSubmit = computed(() => {
    return Boolean(contactForm.type)
        && Boolean(finalLabel.value)
        && Boolean(normalizedContactValue.value);
});

const contactTypeLabel = (type) => {
    return contactTypes.find((item) => item.value === type)?.label ?? type;
};

const contactTypeIcon = (type) => {
    return contactTypes.find((item) => item.value === type)?.icon ?? 'pi pi-link';
};

const contactRows = computed(() => {
    return (props.branch.contacts ?? []).map((contact) => ({
        ...contact,
        type_label: contactTypeLabel(contact.type),
        label_text: contact.label || 'Bez názvu',
        value_text: contact.value || '—',
    }));
});

const contactColumns = [
    {
        field: 'type_label',
        header: 'Typ',
        sortable: true,
    },
    {
        field: 'label_text',
        header: 'Názov',
        sortable: true,
    },
    {
        field: 'value_text',
        header: 'Hodnota',
        sortable: true,
    },
];

const resetContactForm = () => {
    contactForm.reset();

    contactForm.type = 'phone';
    contactForm.label = 'Hlavný telefón';
    contactForm.custom_label = '';
    contactForm.value = '';
    contactForm.is_primary = false;
    contactForm.sort_order = 0;

    selectedPhoneCountryCode.value = 'SK';
    phoneFullValue.value = '';
};

watch(() => contactForm.type, (newType) => {
    contactForm.value = '';
    contactForm.custom_label = '';
    contactForm.label = labelOptionsByType[newType]?.[0]?.value ?? '';
    phoneFullValue.value = '';

    if (['phone', 'booking_phone'].includes(newType)) {
        selectedPhoneCountryCode.value = 'SK';
    }
});

const addContact = () => {
    if (!canSubmit.value) {
        return;
    }

    contactForm
        .transform((data) => ({
            ...data,
            label: finalLabel.value,
            value: normalizedContactValue.value,
            is_primary: Boolean(data.is_primary),
            sort_order: Number(data.sort_order ?? 0),
        }))
        .post(route('branches.contacts.store', props.branch.id), {
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
        confirmSeverity: 'danger',
        onConfirm: () => {
            router.delete(route('branches.contacts.destroy', [props.branch.id, contact.id]), {
                preserveScroll: true,
            });
        },
    });
};

const makePrimaryContact = (contact) => {
    router.put(route('branches.contacts.update', [props.branch.id, contact.id]), {
        type: contact.type,
        label: contact.label ?? '',
        value: contact.value,
        is_primary: true,
        sort_order: contact.sort_order ?? 0,
    }, {
        preserveScroll: true,
    });
};

const addFaqItem = () => {
    faqItems.value.push({
        question: '',
        answer: '',
    });
};

const removeFaqItem = (index) => {
    faqItems.value.splice(index, 1);

    if (!faqItems.value.length) {
        addFaqItem();
    }
};

const saveFaqItems = () => {
    router.put(route('branches.faq-items.update', props.branch.id), {
        faq_items: faqItems.value
            .map((item) => ({
                question: String(item.question || '').trim(),
                answer: String(item.answer || '').trim(),
            }))
            .filter((item) => item.question && item.answer),
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <form @submit.prevent="addContact">
            <FormPage
                :show-submit="false"
                :loading="contactForm.processing"
            >
                <FormSection
                    title="Kontakty"
                    description="Kontakty, kde vás klienti môžu zastihnúť."
                    columns="lg:grid-cols-3"
                >
                    <FormField
                        label="Typ kontaktu"
                        required
                        :error="contactForm.errors.type"
                    >
                        <Select
                            v-model="contactForm.type"
                            :options="contactTypes"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        >
                            <template #value="{ value }">
                                <div class="flex items-center gap-2">
                                    <i
                                        :class="contactTypeIcon(value)"
                                        class="text-sm text-accent"
                                    />

                                    <span>{{ contactTypeLabel(value) }}</span>
                                </div>
                            </template>

                            <template #option="{ option }">
                                <div class="flex items-center gap-2">
                                    <i
                                        :class="option.icon"
                                        class="text-sm text-accent"
                                    />

                                    <span>{{ option.label }}</span>
                                </div>
                            </template>
                        </Select>
                    </FormField>

                    <FormField
                        label="Názov kontaktu"
                        required
                        :error="contactForm.errors.label"
                    >
                        <Select
                            v-model="contactForm.label"
                            :options="labelOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </FormField>

                    <FormField
                        v-if="isCustomLabel"
                        label="Vlastný názov"
                        required
                        :error="contactForm.errors.custom_label"
                    >
                        <InputText
                            v-model="contactForm.custom_label"
                            class="w-full"
                            placeholder="Napr. WhatsApp, LinkedIn..."
                        />
                    </FormField>

                    <FormField
                        :label="selectedType.valueLabel"
                        required
                        :error="contactForm.errors.value"
                        :span="isCustomLabel ? 'lg:col-span-3' : 'lg:col-span-1'"
                    >
                        <PhoneInput
                            v-if="isPhoneType"
                            v-model="contactForm.value"
                            v-model:country-code="selectedPhoneCountryCode"
                            :invalid="Boolean(contactForm.errors.value)"
                            @update:full-value="phoneFullValue = $event"
                        />

                        <InputText
                            v-else
                            v-model="contactForm.value"
                            :type="selectedType.inputType"
                            class="w-full"
                            :placeholder="selectedType.placeholder"
                            :invalid="Boolean(contactForm.errors.value)"
                        />
                    </FormField>

                    <div class="flex justify-end lg:col-span-3">
                        <Button
                            type="submit"
                            label="Pridať kontakt"
                            icon="pi pi-plus"
                            :loading="contactForm.processing"
                            :disabled="!canSubmit || contactForm.processing"
                        />
                    </div>
                </FormSection>
            </FormPage>
        </form>

        <TableCard
            title="Existujúce kontakty"
            description="Zoznam kontaktov priradených k tejto pobočke."
            :rows="contactRows"
            :columns="contactColumns"
            empty-message="Táto pobočka zatiaľ nemá žiadne kontakty."
            show-row-actions
        >
            <template #cell-type_label="{ row }">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-soft text-accent">
                        <i
                            :class="contactTypeIcon(row.type)"
                            class="text-sm"
                        />
                    </div>

                    <span class="text-sm font-medium text-dark">
                        {{ row.type_label }}
                    </span>
                </div>
            </template>

            <template #cell-label_text="{ row }">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-accent">
                        {{ row.label_text }}
                    </span>

                    <span
                        v-if="row.is_primary"
                        class="rounded-md bg-soft px-2 py-1 text-[11px] font-semibold text-accent"
                    >
                        Hlavný
                    </span>
                </div>
            </template>

            <template #cell-value_text="{ row }">
                <span class="text-sm font-medium text-dark">
                    {{ row.value_text }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <Button
                    v-if="!row.is_primary"
                    label="Nastaviť ako hlavný"
                    size="small"
                    severity="secondary"
                    outlined
                    icon="pi pi-star"
                    class="mr-2"
                    @click="makePrimaryContact(row)"
                />

                <Button
                    label="Odstrániť"
                    size="small"
                    severity="danger"
                    outlined
                    @click="deleteContact(row)"
                />
            </template>
        </TableCard>

        <form @submit.prevent="saveFaqItems">
            <FormPage
                :show-submit="false"
                :loading="false"
            >
                <FormSection
                    title="Časté otázky"
                    description="Vyhnite sa opakovaným otázkam klientov a ušetrite čas tým, že zverejníte odpovede na často kladené otázky."
                    columns="lg:grid-cols-2"
                >
                    <div class="lg:col-span-2 space-y-4">
                        <div
                            v-for="(item, index) in faqItems"
                            :key="index"
                            class="rounded-md border border-soft bg-white p-4"
                        >
                            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
                                <div class="grid gap-4 md:grid-cols-1">
                                    <FormField
                                        :label="`Otázka ${index + 1}`"
                                        required
                                    >
                                        <InputText
                                            v-model="item.question"
                                            class="w-full"
                                            placeholder="Napr. Ako sa objednať?"
                                        />
                                    </FormField>

                                    <FormField
                                        :label="`Odpoveď ${index + 1}`"
                                        required
                                    >
                                        <Textarea
                                            v-model="item.answer"
                                            class="w-full"
                                            rows="3"
                                            placeholder="Napr. Zavolajte nám na hlavný kontakt alebo použite kontaktný formulár."
                                        />
                                    </FormField>
                                </div>

                                <div class="flex items-start justify-end">
                                    <Button
                                        severity="danger"
                                        size="small"
                                        icon="pi pi-trash"
                                        outlined
                                        :disabled="faqItems.length === 1"
                                        @click="removeFaqItem(index)"
                                        class="!border-0"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 lg:col-span-2 justify-end">
                        <Button
                            type="button"
                            label="Pridať otázku"
                            icon="pi pi-plus"
                            severity="secondary"
                            outlined
                            @click="addFaqItem"
                        />

                        <Button
                            type="submit"
                            label="Uložiť otázky a odpovede"
                        />
                    </div>
                </FormSection>
            </FormPage>
        </form>

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
    </div>
</template>