<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    mode: {
        type: String,
        default: 'create',
    },
    categories: {
        type: Array,
        default: () => [],
    },
    newCategoryValue: {
        type: String,
        default: '__new_category__',
    },
    title: {
        type: String,
        default: 'Vytvoriť novú službu',
    },
    description: {
        type: String,
        default: 'Kategória a názov služby sú povinné. Ostatné údaje môžete doplniť podľa potreby.',
    },
    submitLabel: {
        type: String,
        default: 'Uložiť službu',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit']);

const isCreateMode = computed(() => props.mode === 'create');

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

const categoryOptions = computed(() => {
    return [
        ...props.categories,
        {
            id: props.newCategoryValue,
            name: 'Pridať novú kategóriu',
        },
    ];
});
<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ title }}
            </h2>

            <p class="mt-1 text-sm leading-6 text-slate-600">
                {{ description }}
            </p>
        </div>

        <form class="space-y-8" @submit.prevent="emit('submit')">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">
                    Náhľad
                </p>

                <div class="mt-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm">
                            <i
                                v-if="props.form.service.icon"
                                :class="props.form.service.icon"
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
                                {{ props.form.service.short_description || 'Krátky popis služby sa zobrazí tu.' }}
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
                            v-if="props.form.prices.self_pay_amount"
                            :value="`${props.form.prices.self_pay_amount} €`"
                            severity="secondary"
                        />
                    </div>
                </div>
            </div>

            <div v-if="isCreateMode" class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Kategória <span class="text-red-500">*</span>
                    </label>

                    <Select
                        v-model="props.form.service.category_id"
                        :options="categoryOptions"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Vyberte kategóriu"
                        class="w-full"
                    />

                    <p
                        v-if="props.form.errors['service.category_id']"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ props.form.errors['service.category_id'] }}
                    </p>
                </div>

                <div v-if="isCreatingNewCategory">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Názov novej kategórie <span class="text-red-500">*</span>
                    </label>

                    <InputText
                        v-model="props.form.service.new_category_name"
                        class="w-full"
                        placeholder="Napr. Diagnostika"
                    />

                    <p
                        v-if="props.form.errors['service.new_category_name']"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ props.form.errors['service.new_category_name'] }}
                    </p>
                </div>

                <div :class="isCreatingNewCategory ? 'md:col-span-2' : ''">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Názov služby <span class="text-red-500">*</span>
                    </label>

                    <InputText
                        v-model="props.form.service.name"
                        class="w-full"
                        placeholder="Napr. Klinicko-psychologické vyšetrenie"
                    />

                    <p
                        v-if="props.form.errors['service.name']"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ props.form.errors['service.name'] }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Ikona
                    </label>

                    <Select
                        v-model="props.form.service.icon"
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
                        v-model="props.form.service.duration_sessions"
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
                        v-model="props.form.service.duration_minutes"
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
                        v-model="props.form.service.short_description"
                        class="w-full"
                        placeholder="Jedna veta, ktorá stručne vysvetlí službu."
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Dlhý popis služby
                    </label>

                    <Textarea
                        v-model="props.form.service.description"
                        class="w-full"
                        rows="5"
                        placeholder="Detailný popis služby..."
                    />
                </div>
            </div>

            <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-slate-900">
                        Základ služby
                    </h3>

                    <p class="mt-1 text-sm text-slate-600">
                        Táto služba je už vytvorená. Tu upravujete len nastavenia pre túto pobočku a ceny.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">
                            Názov služby
                        </p>

                        <p class="mt-1 text-sm font-medium text-slate-900">
                            {{ props.form.service.name || '—' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">
                            Kategória
                        </p>

                        <p class="mt-1 text-sm font-medium text-slate-900">
                            {{ selectedCategoryName }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">
                            Trvanie
                        </p>

                        <p class="mt-1 text-sm font-medium text-slate-900">
                            {{ durationPreview || '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-8">
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ props.form.errors['service.new_category_name'] }}
                        </p>
                    </div>

                    <div :class="isCreatingNewCategory ? 'md:col-span-2' : ''">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Názov služby <span class="text-red-500">*</span>
                        </label>

                        <InputText
                            v-model="props.form.service.name"
                            class="w-full"
                            placeholder="Napr. Klinicko-psychologické vyšetrenie"
                        />

                        <p
                            v-if="props.form.errors['service.name']"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ props.form.errors['service.name'] }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Ikona
                        </label>

                        <Select
                            v-model="props.form.service.icon"
                            :options="iconOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Vyberte ikonu"
                            filter
                            class="w-full"
                        >
                            <template #value="{ value }">
                                <div

                                <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <div class="mb-4">
                                        <h3 class="text-base font-semibold text-slate-900">
                                            Základ služby
                                        </h3>

                                        <p class="mt-1 text-sm text-slate-600">
                                            Táto služba je už vytvorená. Tu upravujete len nastavenia pre túto pobočku a ceny.
                                        </p>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">
                                                Názov služby
                                            </p>

                                            <p class="mt-1 text-sm font-medium text-slate-900">
                                                {{ props.form.service.name || '—' }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">
                                                Kategória
                                            </p>

                                            <p class="mt-1 text-sm font-medium text-slate-900">
                                                {{ selectedCategoryName }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">
                                                Trvanie
                                            </p>

                                            <p class="mt-1 text-sm font-medium text-slate-900">
                                                {{ durationPreview || '—' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
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
                            v-model="props.form.service.duration_sessions"
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
                            v-model="props.form.service.duration_minutes"
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
                            v-model="props.form.service.short_description"
                            class="w-full"
                            placeholder="Jedna veta, ktorá stručne vysvetlí službu."
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Dlhý popis služby
                        </label>

                        <Textarea
                            v-model="props.form.service.description"
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
                        Zobrazenie na pobočke
                    </h3>

                    <p class="mt-1 text-sm text-slate-600">
                        Tieto polia iba upravujú, ako sa služba zobrazuje na tejto pobočke.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Vlastný názov služby na pobočke
                        </label>

                        <InputText
                            v-model="props.form.branch_service.custom_title"
                            class="w-full"
                            placeholder="Napr. Psychologické poradenstvo"
                        />

                        <p
                            v-if="props.form.errors['branch_service.custom_title']"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ props.form.errors['branch_service.custom_title'] }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Poradie na pobočke
                        </label>

                        <InputNumber
                            v-model="props.form.branch_service.sort_order"
                            class="w-full"
                            inputClass="w-full"
                            :min="0"
                            placeholder="0"
                        />

                        <p
                            v-if="props.form.errors['branch_service.sort_order']"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ props.form.errors['branch_service.sort_order'] }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Vlastný popis služby na pobočke
                        </label>

                        <Textarea
                            v-model="props.form.branch_service.custom_description"
                            class="w-full"
                            rows="3"
                            placeholder="Doplňujúci text špecifický pre túto pobočku"
                        />

                        <p
                            v-if="props.form.errors['branch_service.custom_description']"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ props.form.errors['branch_service.custom_description'] }}
                        </p>
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <Checkbox
                            v-model="props.form.branch_service.is_available"
                            binary
                            inputId="branch_service_is_available"
                        />

                        <label
                            for="branch_service_is_available"
                            class="text-sm font-medium text-slate-700"
                        >
                            Služba je na tejto pobočke dostupná
                        </label>
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
                                        v-model="props.form.prices.insurance_amount"
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
                                v-model="props.form.prices.insurance_note"
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
                                        v-model="props.form.prices.self_pay_amount"
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
                                v-model="props.form.prices.self_pay_note"
                                class="w-full"
                                placeholder="Poznámka k cene"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Slug a technické poradie sa vyriešia automaticky.
                </p>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p
                        v-if="props.form.service.slug || generatedSlug"
                        class="text-xs text-slate-400"
                    >
                        {{ generatedSlug || props.form.service.slug }}
                    </p>

                    <Button
                        type="submit"
                        :label="submitLabel"
                        icon="pi pi-save"
                        :loading="loading || props.form.processing"
                        :disabled="!canSubmit"
                    />
                </div>
            </div>
        </form>
    </section>
</template>
