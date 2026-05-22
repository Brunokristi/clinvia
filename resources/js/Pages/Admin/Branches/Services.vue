<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

const props = defineProps({
    branch: Object,
    categories: {
        type: Array,
        default: () => [],
    },
});

const newCategoryValue = '__new_category__';

const serviceForm = useForm({
    service: {
        category_id: null,
        new_category_name: '',
        name: '',
        slug: '',
        short_description: '',
        description: '',
        icon: '',
        duration_sessions: 1,
        duration_minutes: null,
        is_active: true,
        sort_order: 0,
    },

    branch_service: {
        custom_title: '',
        is_available: true,
        sort_order: 0,
    },

    prices: {
        insurance_amount: null,
        insurance_note: '',
        self_pay_amount: null,
        self_pay_note: '',
    },

    information: [
        {
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ],

    necessities: [
        {
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ],

    steps: [
        {
            number: 1,
            title: '',
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ],

    tags: [],
    tag_name: '',
    files: [],
});

const categoryOptions = computed(() => {
    return [
        ...props.categories,
        {
            id: newCategoryValue,
            name: 'Pridať novú kategóriu',
        },
    ];
});

const iconOptions = [
    { label: 'Psychológia', value: 'pi pi-heart' },
    { label: 'Zdravie', value: 'pi pi-plus-circle' },
    { label: 'Vyšetrenie', value: 'pi pi-search' },
    { label: 'Konzultácia', value: 'pi pi-comments' },
    { label: 'Terapia', value: 'pi pi-users' },
    { label: 'Dieťa / rodina', value: 'pi pi-face-smile' },
    { label: 'Dokument', value: 'pi pi-file' },
    { label: 'Čas', value: 'pi pi-clock' },
    { label: 'Kalendár', value: 'pi pi-calendar' },
    { label: 'Telefón', value: 'pi pi-phone' },
    { label: 'Email', value: 'pi pi-envelope' },
    { label: 'Upozornenie', value: 'pi pi-info-circle' },
    { label: 'Dôležité', value: 'pi pi-exclamation-circle' },
    { label: 'Hviezda', value: 'pi pi-star' },
    { label: 'Štítok', value: 'pi pi-tag' },
    { label: 'Mapa', value: 'pi pi-map-marker' },
    { label: 'Budova', value: 'pi pi-building' },
    { label: 'Aktovka', value: 'pi pi-briefcase' },
    { label: 'Kľúč', value: 'pi pi-key' },
    { label: 'Nastavenia', value: 'pi pi-cog' },
];

const slugify = (value) => {
    return (value ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const generatedSlug = computed(() => {
    return slugify(serviceForm.service.name);
});

const isCreatingNewCategory = computed(() => {
    return serviceForm.service.category_id === newCategoryValue;
});

const selectedCategoryName = computed(() => {
    if (isCreatingNewCategory.value) {
        return serviceForm.service.new_category_name || 'Nová kategória';
    }

    return props.categories.find((category) => category.id === serviceForm.service.category_id)?.name || 'Bez kategórie';
});

const serviceTitlePreview = computed(() => {
    return serviceForm.branch_service.custom_title
        || serviceForm.service.name
        || 'Názov služby';
});

const durationPreview = computed(() => {
    const sessions = serviceForm.service.duration_sessions;
    const minutes = serviceForm.service.duration_minutes;

    if (!sessions || !minutes) {
        return '';
    }

    if (sessions === 1) {
        return `${minutes} min`;
    }

    return `${sessions} × ${minutes} min`;
});

const canSubmit = computed(() => {
    const hasCategory = isCreatingNewCategory.value
        ? Boolean(serviceForm.service.new_category_name.trim())
        : Boolean(serviceForm.service.category_id);

    return hasCategory && Boolean(serviceForm.service.name.trim());
});

const branchServiceTitle = (branchService) => {
    return branchService.custom_title || branchService.service?.name || '—';
};

const serviceDisplayName = (service) => {
    return service?.name ?? '—';
};

const getServicePrice = (branchService, type) => {
    return branchService.prices?.find((price) => price.price_type === type) ?? null;
};

const formatPrice = (price) => {
    if (!price || price.amount === null || price.amount === undefined) {
        return '—';
    }

    return `${price.amount} ${price.currency ?? 'EUR'}`;
};

const addInformationItem = () => {
    serviceForm.information.push({
        text: '',
        is_active: true,
        sort_order: serviceForm.information.length,
    });
};

const removeInformationItem = (index) => {
    serviceForm.information.splice(index, 1);

    serviceForm.information = serviceForm.information.map((item, itemIndex) => ({
        ...item,
        sort_order: itemIndex,
    }));
};

const addNecessityItem = () => {
    serviceForm.necessities.push({
        text: '',
        is_active: true,
        sort_order: serviceForm.necessities.length,
    });
};

const removeNecessityItem = (index) => {
    serviceForm.necessities.splice(index, 1);

    serviceForm.necessities = serviceForm.necessities.map((item, itemIndex) => ({
        ...item,
        sort_order: itemIndex,
    }));
};

const addStep = () => {
    serviceForm.steps.push({
        number: serviceForm.steps.length + 1,
        title: '',
        text: '',
        is_active: true,
        sort_order: serviceForm.steps.length,
    });
};

const removeStep = (index) => {
    serviceForm.steps.splice(index, 1);

    serviceForm.steps = serviceForm.steps.map((step, stepIndex) => ({
        ...step,
        number: stepIndex + 1,
        sort_order: stepIndex,
    }));
};

const addTag = () => {
    const tagName = serviceForm.tag_name.trim();

    if (!tagName) {
        return;
    }

    const alreadyExists = serviceForm.tags.some((tag) => {
        return tag.name.toLowerCase() === tagName.toLowerCase();
    });

    if (alreadyExists) {
        serviceForm.tag_name = '';
        return;
    }

    serviceForm.tags.push({
        name: tagName,
        sort_order: serviceForm.tags.length,
    });

    serviceForm.tag_name = '';
};

const removeTag = (index) => {
    serviceForm.tags.splice(index, 1);

    serviceForm.tags = serviceForm.tags.map((tag, tagIndex) => ({
        ...tag,
        sort_order: tagIndex,
    }));
};

const handleFiles = (event) => {
    const selectedFiles = event.files ?? [];

    selectedFiles.forEach((file) => {
        serviceForm.files.push({
            label: file.name,
            file,
            is_active: true,
            sort_order: serviceForm.files.length,
        });
    });
};

const removeFile = (index) => {
    serviceForm.files.splice(index, 1);

    serviceForm.files = serviceForm.files.map((file, fileIndex) => ({
        ...file,
        sort_order: fileIndex,
    }));
};

const resetForm = () => {
    serviceForm.reset();

    serviceForm.service.is_active = true;
    serviceForm.service.sort_order = 0;
    serviceForm.service.duration_sessions = 1;

    serviceForm.branch_service.is_available = true;
    serviceForm.branch_service.sort_order = 0;

    serviceForm.information = [
        {
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ];

    serviceForm.necessities = [
        {
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ];

    serviceForm.steps = [
        {
            number: 1,
            title: '',
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ];

    serviceForm.tags = [];
    serviceForm.tag_name = '';
    serviceForm.files = [];
};

const createFullService = () => {
    // prepare flat payload to match BranchServiceController@store
    const payload = {
        create_new: true,
        category_id: serviceForm.service.category_id === newCategoryValue ? null : serviceForm.service.category_id,
        new_category_name: serviceForm.service.new_category_name,
        name: serviceForm.service.name,
        slug: generatedSlug.value,
        short_description: serviceForm.service.short_description,
        description: serviceForm.service.description,
        icon: serviceForm.service.icon,
        duration_minutes: serviceForm.service.duration_minutes,

        custom_title: serviceForm.branch_service.custom_title,
        custom_description: serviceForm.branch_service.custom_description,
        is_available: serviceForm.branch_service.is_available,
        sort_order: serviceForm.branch_service.sort_order,

        insurance_amount: serviceForm.prices.insurance_amount,
        insurance_note: serviceForm.prices.insurance_note,
        self_pay_amount: serviceForm.prices.self_pay_amount,
        self_pay_note: serviceForm.prices.self_pay_note,
    };

    serviceForm
        .transform(() => payload)
        .post(route('branches.services.store', props.branch.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                resetForm();
            },
        });
};

const removeBranchService = (branchService) => {
    if (!confirm(`Odstrániť službu ${branchServiceTitle(branchService)} z tejto pobočky?`)) {
        return;
    }

    router.delete(route('branches.services.destroy', [props.branch.id, branchService.id]), {
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
                    Služby pobočky
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Vytvorte kompletnú službu pre túto pobočku naraz.
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
                        Vytvoriť novú službu
                    </h2>

                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Kategória a názov služby sú povinné. Ostatné údaje môžete doplniť podľa potreby.
                    </p>
                </div>

                <form class="space-y-8" @submit.prevent="createFullService">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">
                            Náhľad
                        </p>

                        <div class="mt-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm">
                                    <i
                                        v-if="serviceForm.service.icon"
                                        :class="serviceForm.service.icon"
                                    />

                                    <span v-else class="text-sm font-semibold">
                                        S
                                    </span>
                                </div>

                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">
                                        {{ serviceTitlePreview }}
                                    </h3>

                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ selectedCategoryName }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ serviceForm.service.short_description || 'Krátky popis služby sa zobrazí tu.' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Tag
                                    v-if="durationPreview"
                                    :value="durationPreview"
                                    severity="secondary"
                                />

                                <Tag
                                    v-if="serviceForm.prices.self_pay_amount"
                                    :value="`${serviceForm.prices.self_pay_amount} €`"
                                    severity="secondary"
                                />
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="mb-5">
                            <h3 class="text-base font-semibold text-slate-900">
                                Základné údaje
                            </h3>

                            <p class="mt-1 text-sm text-slate-600">
                                Tieto údaje tvoria hlavný profil služby.
                            </p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Kategória <span class="text-red-500">*</span>
                                </label>

                                <Select
                                    v-model="serviceForm.service.category_id"
                                    :options="categoryOptions"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Vyberte kategóriu"
                                    class="w-full"
                                />

                                <p
                                    v-if="serviceForm.errors['service.category_id']"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ serviceForm.errors['service.category_id'] }}
                                </p>
                            </div>

                            <div v-if="isCreatingNewCategory">
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Názov novej kategórie <span class="text-red-500">*</span>
                                </label>

                                <InputText
                                    v-model="serviceForm.service.new_category_name"
                                    class="w-full"
                                    placeholder="Napr. Diagnostika"
                                />

                                <p
                                    v-if="serviceForm.errors['service.new_category_name']"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ serviceForm.errors['service.new_category_name'] }}
                                </p>
                            </div>

                            <div :class="isCreatingNewCategory ? 'md:col-span-2' : ''">
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Názov služby <span class="text-red-500">*</span>
                                </label>

                                <InputText
                                    v-model="serviceForm.service.name"
                                    class="w-full"
                                    placeholder="Napr. Klinicko-psychologické vyšetrenie"
                                />

                                <p
                                    v-if="serviceForm.errors['service.name']"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ serviceForm.errors['service.name'] }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Ikona
                                </label>

                                <Select
                                    v-model="serviceForm.service.icon"
                                    :options="iconOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Vyberte ikonu"
                                    filter
                                    class="w-full"
                                >
                                    <template #value="{ value }">
                                        <div
                                            v-if="value"
                                            class="flex items-center gap-2"
                                        >
                                            <i :class="value" />
                                            <span>{{ iconOptions.find((item) => item.value === value)?.label ?? value }}</span>
                                        </div>

                                        <span v-else class="text-slate-400">
                                            Vyberte ikonu
                                        </span>
                                    </template>

                                    <template #option="{ option }">
                                        <div class="flex items-center gap-2">
                                            <i :class="option.value" />
                                            <span>{{ option.label }}</span>
                                        </div>
                                    </template>
                                </Select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Počet stretnutí
                                </label>

                                <InputNumber
                                    v-model="serviceForm.service.duration_sessions"
                                    class="w-full"
                                    inputClass="w-full"
                                    :min="1"
                                    placeholder="1"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Minút na jedno stretnutie
                                </label>

                                <InputNumber
                                    v-model="serviceForm.service.duration_minutes"
                                    class="w-full"
                                    inputClass="w-full"
                                    :min="1"
                                    placeholder="60"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Krátky popis
                                </label>

                                <InputText
                                    v-model="serviceForm.service.short_description"
                                    class="w-full"
                                    placeholder="Jedna veta, ktorá stručne vysvetlí službu."
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Dlhý popis služby
                                </label>

                                <Textarea
                                    v-model="serviceForm.service.description"
                                    class="w-full"
                                    rows="5"
                                    placeholder="Detailný popis služby..."
                                />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-8">
                        <div class="mb-5">
                            <h3 class="text-base font-semibold text-slate-900">
                                Ceny
                            </h3>

                            <p class="mt-1 text-sm text-slate-600">
                                Zadajte cenu cez poisťovňu alebo cenu pre samoplatcu.
                            </p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <h4 class="text-sm font-semibold text-slate-900">
                                    Poisťovňa
                                </h4>

                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">
                                            Cena
                                        </label>

                                        <div class="flex overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
                                            <InputNumber
                                                v-model="serviceForm.prices.insurance_amount"
                                                class="min-w-0 flex-1"
                                                inputClass="w-full border-0 shadow-none"
                                                mode="decimal"
                                                :minFractionDigits="2"
                                                :maxFractionDigits="2"
                                                placeholder="0.00"
                                            />

                                            <div class="flex items-center border-l border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-600">
                                                €
                                            </div>
                                        </div>
                                    </div>

                                    <InputText
                                        v-model="serviceForm.prices.insurance_note"
                                        class="w-full"
                                        placeholder="Poznámka k cene"
                                    />
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <h4 class="text-sm font-semibold text-slate-900">
                                    Samoplatca
                                </h4>

                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">
                                            Cena
                                        </label>

                                        <div class="flex overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
                                            <InputNumber
                                                v-model="serviceForm.prices.self_pay_amount"
                                                class="min-w-0 flex-1"
                                                inputClass="w-full border-0 shadow-none"
                                                mode="decimal"
                                                :minFractionDigits="2"
                                                :maxFractionDigits="2"
                                                placeholder="0.00"
                                            />

                                            <div class="flex items-center border-l border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-600">
                                                €
                                            </div>
                                        </div>
                                    </div>

                                    <InputText
                                        v-model="serviceForm.prices.self_pay_note"
                                        class="w-full"
                                        placeholder="Poznámka k cene"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-8">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">
                                    Informácie
                                </h3>

                                <p class="mt-1 text-sm text-slate-600">
                                    Krátke informačné body, ktoré sa zobrazia pri službe.
                                </p>
                            </div>

                            <Button
                                type="button"
                                label="Pridať informáciu"
                                icon="pi pi-plus"
                                size="small"
                                outlined
                                @click="addInformationItem"
                            />
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(item, index) in serviceForm.information"
                                :key="index"
                                class="grid gap-3 md:grid-cols-[1fr_auto]"
                            >
                                <Textarea
                                    v-model="item.text"
                                    rows="2"
                                    placeholder="Napr. Vyšetrenie trvá približne 60 minút."
                                />

                                <Button
                                    v-if="serviceForm.information.length > 1"
                                    type="button"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    @click="removeInformationItem(index)"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-8">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">
                                    Čo klient potrebuje
                                </h3>

                                <p class="mt-1 text-sm text-slate-600">
                                    Veci, ktoré si má klient priniesť alebo pripraviť.
                                </p>
                            </div>

                            <Button
                                type="button"
                                label="Pridať položku"
                                icon="pi pi-plus"
                                size="small"
                                outlined
                                @click="addNecessityItem"
                            />
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(item, index) in serviceForm.necessities"
                                :key="index"
                                class="grid gap-3 md:grid-cols-[1fr_auto]"
                            >
                                <InputText
                                    v-model="item.text"
                                    placeholder="Napr. Kartička poistenca"
                                />

                                <Button
                                    v-if="serviceForm.necessities.length > 1"
                                    type="button"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    @click="removeNecessityItem(index)"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-8">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">
                                    Postup služby
                                </h3>

                                <p class="mt-1 text-sm text-slate-600">
                                    Kroky, ktoré klient absolvuje.
                                </p>
                            </div>

                            <Button
                                type="button"
                                label="Pridať krok"
                                icon="pi pi-plus"
                                size="small"
                                outlined
                                @click="addStep"
                            />
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(step, index) in serviceForm.steps"
                                :key="index"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="mb-4 flex items-center justify-between gap-4">
                                    <h4 class="text-sm font-semibold text-slate-900">
                                        Krok {{ index + 1 }}
                                    </h4>

                                    <Button
                                        v-if="serviceForm.steps.length > 1"
                                        type="button"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        rounded
                                        @click="removeStep(index)"
                                    />
                                </div>

                                <div class="grid gap-4 md:grid-cols-[120px_1fr]">
                                    <InputNumber
                                        v-model="step.number"
                                        inputClass="w-full"
                                        placeholder="Číslo"
                                    />

                                    <InputText
                                        v-model="step.title"
                                        placeholder="Názov kroku"
                                    />

                                    <Textarea
                                        v-model="step.text"
                                        class="md:col-span-2"
                                        rows="3"
                                        placeholder="Popis kroku"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-8">
                        <div class="mb-5">
                            <h3 class="text-base font-semibold text-slate-900">
                                Tagy
                            </h3>

                            <p class="mt-1 text-sm text-slate-600">
                                Pomôžu filtrovať alebo označiť službu.
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <InputText
                                v-model="serviceForm.tag_name"
                                class="flex-1"
                                placeholder="Napr. diagnostika, terapia, deti"
                                @keydown.enter.prevent="addTag"
                            />

                            <Button
                                type="button"
                                label="Pridať tag"
                                icon="pi pi-plus"
                                outlined
                                @click="addTag"
                            />
                        </div>

                        <div
                            v-if="serviceForm.tags.length"
                            class="mt-4 flex flex-wrap gap-2"
                        >
                            <button
                                v-for="(tag, index) in serviceForm.tags"
                                :key="index"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700 transition hover:bg-red-50 hover:text-red-600"
                                @click="removeTag(index)"
                            >
                                {{ tag.name }}
                                <i class="pi pi-times text-xs" />
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-8">
                        <div class="mb-5">
                            <h3 class="text-base font-semibold text-slate-900">
                                Dokumenty
                            </h3>

                            <p class="mt-1 text-sm text-slate-600">
                                Nahrajte dokumenty alebo formuláre k službe.
                            </p>
                        </div>

                        <FileUpload
                            mode="basic"
                            name="files"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            chooseLabel="Vybrať dokumenty"
                            multiple
                            customUpload
                            auto
                            @select="handleFiles"
                        />

                        <div
                            v-if="serviceForm.files.length"
                            class="mt-4 space-y-3"
                        >
                            <div
                                v-for="(fileItem, index) in serviceForm.files"
                                :key="index"
                                class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_1fr_auto]"
                            >
                                <InputText
                                    v-model="fileItem.label"
                                    placeholder="Názov dokumentu"
                                />

                                <div class="flex items-center text-sm text-slate-600">
                                    {{ fileItem.file.name }}
                                </div>

                                <Button
                                    type="button"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    @click="removeFile(index)"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">
                            Slug a technické poradie sa vyriešia automaticky.
                        </p>

                        <Button
                            type="submit"
                            label="Vytvoriť službu"
                            icon="pi pi-save"
                            :loading="serviceForm.processing"
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
                                Existujúce služby v pobočke
                            </h2>

                            <p class="mt-1 text-sm text-slate-600">
                                Služby, ktoré sú už priradené k tejto pobočke.
                            </p>
                        </div>

                        <Tag
                            :value="`${branch.branch_services?.length ?? 0} služieb`"
                            severity="secondary"
                        />
                    </div>
                </div>

                <DataTable
                    :value="branch.branch_services ?? []"
                    tableStyle="min-width: 60rem"
                    emptyMessage="Táto pobočka zatiaľ nemá priradené služby."
                >
                    <Column header="Služba">
                        <template #body="{ data }">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ branchServiceTitle(data) }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    Pôvodný názov: {{ serviceDisplayName(data.service) }}
                                </p>

                                <p
                                    v-if="data.service?.category"
                                    class="text-xs text-slate-500"
                                >
                                    Kategória: {{ data.service.category.name }}
                                </p>
                            </div>
                        </template>
                    </Column>

                    <Column header="Ceny">
                        <template #body="{ data }">
                            <div class="space-y-1 text-sm text-slate-700">
                                <p>
                                    Poisťovňa: {{ formatPrice(getServicePrice(data, 'insurance')) }}
                                </p>

                                <p>
                                    Samoplatca: {{ formatPrice(getServicePrice(data, 'self_pay')) }}
                                </p>
                            </div>
                        </template>
                    </Column>

                    <Column header="Dostupnosť">
                        <template #body="{ data }">
                            <Tag
                                :value="data.is_available ? 'Dostupná' : 'Nedostupná'"
                                :severity="data.is_available ? 'success' : 'secondary'"
                            />
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
                                @click="removeBranchService(data)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </section>
        </div>
    </AdminLayout>
</template>