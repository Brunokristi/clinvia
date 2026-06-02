<script setup>
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';

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
    submitLabel: {
        type: String,
        default: 'Uložiť službu',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['submit']);

const form = props.form;

const draggedStepIndex = ref(null);

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

const isCreatingNewCategory = computed(() => {
    return form.category_id === props.newCategoryValue;
});

const fileNameFor = (item) => {
    return item.file?.name || item.existing_name || 'Vybrať súbor';
};

const addInformation = () => {
    form.information.push({
        existing_id: null,
        text: '',
    });
};

const addStep = () => {
    form.steps.push({
        existing_id: null,
        number: form.steps.length + 1,
        title: '',
        text: '',
    });
};

const addFile = () => {
    form.files.push({
        existing_id: null,
        label: '',
        file: null,
        existing_name: '',
    });
};

const removeItem = (collection, index) => {
    collection.splice(index, 1);
    normalizeCollections();
};

const handleFileChange = (item, event) => {
    item.file = event.target.files?.[0] ?? null;
};

const handleStepDragStart = (index, event) => {
    draggedStepIndex.value = index;

    try {
        event.dataTransfer.setData('text/plain', String(index));
        event.dataTransfer.effectAllowed = 'move';
    } catch (e) {
        // Ignore if dataTransfer is not available.
    }
};

const handleStepDragEnd = () => {
    draggedStepIndex.value = null;
};

const handleStepDrop = (dropIndex) => {
    if (draggedStepIndex.value === null || draggedStepIndex.value === dropIndex) {
        draggedStepIndex.value = null;

        return;
    }

    const draggedItem = form.steps.splice(draggedStepIndex.value, 1)[0];

    form.steps.splice(dropIndex, 0, draggedItem);

    draggedStepIndex.value = null;

    normalizeCollections();
};

const normalizeCollections = () => {
    form.is_available = true;
    form.sort_order = 0;

    form.information = form.information.map((item, index) => ({
        ...item,
        sort_order: index,
        is_active: true,
    }));

    form.steps = form.steps.map((item, index) => ({
        ...item,
        number: index + 1,
        sort_order: index,
        is_active: true,
    }));

    form.files = form.files.map((item, index) => ({
        ...item,
        sort_order: index,
        is_active: true,
    }));
};

const submitForm = () => {
    normalizeCollections();

    emit('submit');
};

const canSubmit = computed(() => {
    if (!isCreateMode.value) {
        return true;
    }

    const hasCategory = form.category_id !== null && form.category_id !== undefined;
    const hasNewCategoryName = form.category_id !== props.newCategoryValue
        || Boolean((form.new_category_name ?? '').trim());
    const hasName = Boolean((form.name ?? '').trim());

    return hasCategory && hasNewCategoryName && hasName;
});

const iconLabel = (value) => {
    return iconOptions.find((item) => item.value === value)?.label ?? value;
};
</script>

<template>
    <form @submit.prevent="submitForm">
        <FormPage
            :submit-label="submitLabel"
            :loading="loading || form.processing"
        >
            <FormSection
                title="Základné informácie"
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Názov služby"
                    for="name"
                    required
                    :error="form.errors.name"
                    span="md:col-span-2"
                >
                    <InputText
                        id="name"
                        v-model="form.name"
                        class="w-full"
                        placeholder="Napr. Klinicko-psychologické vyšetrenie"
                        :invalid="Boolean(form.errors.name)"
                    />
                </FormField>

                <FormField
                    label="Kategória"
                    for="category_id"
                    required
                    :error="form.errors.category_id"
                    :span="isCreatingNewCategory ? '' : 'md:col-span-2'"
                >
                    <Select
                        id="category_id"
                        v-model="form.category_id"
                        :options="categories"
                        option-label="name"
                        option-value="id"
                        placeholder="Vyberte kategóriu"
                        class="w-full"
                        :invalid="Boolean(form.errors.category_id)"
                    />
                </FormField>

                <FormField
                    v-if="isCreatingNewCategory"
                    label="Názov novej kategórie"
                    for="new_category_name"
                    required
                    :error="form.errors.new_category_name"
                >
                    <InputText
                        id="new_category_name"
                        v-model="form.new_category_name"
                        class="w-full"
                        placeholder="Napr. Diagnostika"
                        :invalid="Boolean(form.errors.new_category_name)"
                    />
                </FormField>

                <FormField
                    label="Počet stretnutí"
                    for="duration_sessions"
                    :error="form.errors.duration_sessions"
                >
                    <InputNumber
                        id="duration_sessions"
                        v-model="form.duration_sessions"
                        class="w-full"
                        input-class="w-full"
                        :min="1"
                        placeholder="1"
                        :invalid="Boolean(form.errors.duration_sessions)"
                    />
                </FormField>

                <FormField
                    label="Minút na jedno stretnutie"
                    for="duration_minutes"
                    :error="form.errors.duration_minutes"
                >
                    <InputNumber
                        id="duration_minutes"
                        v-model="form.duration_minutes"
                        class="w-full"
                        input-class="w-full"
                        :min="1"
                        placeholder="60"
                        :invalid="Boolean(form.errors.duration_minutes)"
                    />
                </FormField>

                <FormField
                    label="Rezervácia"
                    for="is_bookable"
                    :error="form.errors.is_bookable"
                    span="md:col-span-2"
                >
                    <div class="flex items-center gap-3">
                        <Checkbox
                            id="is_bookable"
                            v-model="form.is_bookable"
                            binary
                            :invalid="Boolean(form.errors.is_bookable)"
                        />

                        <div>
                            <label
                                for="is_bookable"
                                class="cursor-pointer text-normal text-accent"
                            >
                                Rezervovateľná služba
                            </label>
                        </div>
                    </div>
                </FormField>

                <FormField
                    label="Ikona"
                    for="icon"
                    :error="form.errors.icon"
                    span="md:col-span-2"
                >
                    <Select
                        id="icon"
                        v-model="form.icon"
                        :options="iconOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte ikonu"
                        filter
                        class="w-full"
                        :invalid="Boolean(form.errors.icon)"
                    >
                        <template #value="{ value }">
                            <div
                                v-if="value"
                                class="flex items-center gap-2"
                            >
                                <i :class="value" />
                                <span>{{ iconLabel(value) }}</span>
                            </div>

                            <span
                                v-else
                                class="text-accent/60"
                            >
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
                </FormField>

                <FormField
                    label="Krátky popis"
                    for="short_description"
                    :error="form.errors.short_description"
                    span="md:col-span-2"
                >
                    <InputText
                        id="short_description"
                        v-model="form.short_description"
                        class="w-full"
                        placeholder="Jedna veta, ktorá stručne vysvetlí službu."
                        :invalid="Boolean(form.errors.short_description)"
                    />
                </FormField>

                <FormField
                    label="Dlhý popis"
                    for="description"
                    :error="form.errors.description"
                    span="md:col-span-2"
                >
                    <Textarea
                        id="description"
                        v-model="form.description"
                        class="w-full"
                        rows="4"
                        placeholder="Detailný popis služby..."
                        :invalid="Boolean(form.errors.description)"
                    />
                </FormField>
            </FormSection>

            <FormSection
                title="Ceny"
                columns="md:grid-cols-2"
            >
                <div class="rounded-md border !border-accent p-4">
                    <h4 class="text-sm font-semibold text-dark">
                        Poisťovňa
                    </h4>

                    <div class="mt-4 space-y-4">
                        <FormField
                            label="Cena"
                            for="insurance_amount"
                            :error="form.errors.insurance_amount"
                        >
                            <InputNumber
                                id="insurance_amount"
                                v-model="form.insurance_amount"
                                class="w-full"
                                input-class="w-full"
                                mode="decimal"
                                :min-fraction-digits="2"
                                :max-fraction-digits="2"
                                placeholder="0.00"
                                suffix=" €"
                                :invalid="Boolean(form.errors.insurance_amount)"
                            />
                        </FormField>

                        <FormField
                            label="Poznámka k cene"
                            for="insurance_note"
                            :error="form.errors.insurance_note"
                        >
                            <InputText
                                id="insurance_note"
                                v-model="form.insurance_note"
                                class="w-full"
                                placeholder="Poznámka k cene"
                                :invalid="Boolean(form.errors.insurance_note)"
                            />
                        </FormField>
                    </div>
                </div>

                <div class="rounded-md border !border-accent p-4">
                    <h4 class="text-sm font-semibold text-dark">
                        Samoplatca
                    </h4>

                    <div class="mt-4 space-y-4">
                        <FormField
                            label="Cena"
                            for="self_pay_amount"
                            :error="form.errors.self_pay_amount"
                        >
                            <InputNumber
                                id="self_pay_amount"
                                v-model="form.self_pay_amount"
                                class="w-full"
                                input-class="w-full"
                                mode="decimal"
                                :min-fraction-digits="2"
                                :max-fraction-digits="2"
                                placeholder="0.00"
                                suffix=" €"
                                :invalid="Boolean(form.errors.self_pay_amount)"
                            />
                        </FormField>

                        <FormField
                            label="Poznámka k cene"
                            for="self_pay_note"
                            :error="form.errors.self_pay_note"
                        >
                            <InputText
                                id="self_pay_note"
                                v-model="form.self_pay_note"
                                class="w-full"
                                placeholder="Poznámka k cene"
                                :invalid="Boolean(form.errors.self_pay_note)"
                            />
                        </FormField>
                    </div>
                </div>
            </FormSection>

            <FormSection
                title="Informácie"
                description="Krátke bloky informácií, ktoré sa zobrazia pri službe."
                columns="grid-cols-1"
            >
                <div class="space-y-4">
                    <div
                        v-for="(item, index) in form.information"
                        :key="item.existing_id ?? index"
                    >
                        <div class="grid gap-4">
                            <FormField
                                label="Text informácie"
                                :for="`information_text_${index}`"
                                :error="form.errors[`information.${index}.text`]"
                            >
                                <Textarea
                                    :id="`information_text_${index}`"
                                    v-model="item.text"
                                    class="w-full"
                                    rows="3"
                                    placeholder="Popis alebo poznámka k službe"
                                    :invalid="Boolean(form.errors[`information.${index}.text`])"
                                />
                            </FormField>

                            <div class="flex justify-end">
                                <Button
                                    type="button"
                                    label="Odstrániť"
                                    severity="danger"
                                    outlined
                                    @click="removeItem(form.information, index)"
                                />
                            </div>
                        </div>
                    </div>

                    <Button
                        type="button"
                        label="Pridať informáciu"
                        outlined
                        size="small"
                        @click="addInformation"
                    />
                </div>
            </FormSection>

            <FormSection
                title="Kroky"
                description="Poradie krokov zmeníte potiahnutím karty vyššie alebo nižšie."
                columns="grid-cols-1"
            >
                <div class="space-y-4">
                    <div
                        v-for="(item, index) in form.steps"
                        :key="item.existing_id ?? index"
                        draggable="true"
                        class="rounded-md border border-soft bg-white p-4 transition hover:bg-soft/30"
                        :class="draggedStepIndex === index ? 'opacity-50' : ''"
                        @dragstart="handleStepDragStart(index, $event)"
                        @dragend="handleStepDragEnd"
                        @dragover.prevent
                        @drop="handleStepDrop(index)"
                    >
                        <div class="mb-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <p class="text-sm font-semibold text-dark">
                                    Krok
                                </p>

                                <div class="flex h-9 w-9 items-center justify-center rounded-md bg-soft text-sm font-semibold text-accent">
                                    {{ index + 1 }}
                                </div>
                            </div>

                            <i class="pi pi-bars cursor-grab text-accent" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <FormField
                                label="Názov kroku"
                                :for="`step_title_${index}`"
                                :error="form.errors[`steps.${index}.title`]"
                                span="md:col-span-2"
                            >
                                <InputText
                                    :id="`step_title_${index}`"
                                    v-model="item.title"
                                    class="w-full"
                                    placeholder="Napr. Úvodné vyšetrenie"
                                />
                            </FormField>

                            <FormField
                                label="Popis kroku"
                                :for="`step_text_${index}`"
                                :error="form.errors[`steps.${index}.text`]"
                                span="md:col-span-2"
                            >
                                <Textarea
                                    :id="`step_text_${index}`"
                                    v-model="item.text"
                                    class="w-full"
                                    rows="3"
                                    placeholder="Stručný popis kroku"
                                />
                            </FormField>

                            <div class="flex justify-end md:col-span-2">
                                <Button
                                    type="button"
                                    label="Odstrániť"
                                    severity="danger"
                                    outlined
                                    @click="removeItem(form.steps, index)"
                                />
                            </div>
                        </div>
                    </div>

                    <Button
                        type="button"
                        label="Pridať krok"
                        outlined
                        size="small"
                        @click="addStep"
                    />
                </div>
            </FormSection>

            <FormSection
                title="Dokumenty"
                description="Nahrajte súbory k službe."
                columns="grid-cols-1"
            >
                <div class="space-y-4">
                    <div
                        v-for="(item, index) in form.files"
                        :key="item.existing_id ?? index"
                        class="rounded-md border border-soft p-4"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <FormField
                                label="Názov dokumentu"
                                :for="`file_label_${index}`"
                                :error="form.errors[`files.${index}.label`]"
                            >
                                <InputText
                                    :id="`file_label_${index}`"
                                    v-model="item.label"
                                    class="w-full"
                                    placeholder="Napr. Cenník"
                                />
                            </FormField>

                            <FormField
                                label="Súbor"
                                :error="form.errors[`files.${index}.file`]"
                            >
                                <input
                                    type="file"
                                    class="block w-full text-sm text-accent file:mr-4 file:rounded-md file:border-0 file:bg-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-dark hover:file:bg-soft/80"
                                    @change="handleFileChange(item, $event)"
                                >

                                <p class="mt-2 text-xs text-accent">
                                    {{ fileNameFor(item) }}
                                </p>
                            </FormField>

                            <div class="flex justify-end md:col-span-2">
                                <Button
                                    type="button"
                                    label="Odstrániť"
                                    severity="danger"
                                    outlined
                                    @click="removeItem(form.files, index)"
                                />
                            </div>
                        </div>
                    </div>

                    <Button
                        type="button"
                        label="Pridať dokument"
                        outlined
                        size="small"
                        @click="addFile"
                    />
                </div>
            </FormSection>
        </FormPage>
    </form>
</template>