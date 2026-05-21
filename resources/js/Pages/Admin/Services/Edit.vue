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
    service: Object,
    categories: Array,
});

const form = useForm({
    category_id: props.service.category_id,
    name: props.service.name ?? '',
    slug: props.service.slug ?? '',
    short_description: props.service.short_description ?? '',
    description: props.service.description ?? '',
    icon: props.service.icon ?? '',
    duration_minutes: props.service.duration_minutes ?? null,
    is_active: Boolean(props.service.is_active),
    sort_order: props.service.sort_order ?? 0,
});

const submit = () => {
    form.put(route('services.update', props.service.id), {
        preserveScroll: true,
    });
};

const informationForm = useForm({
    text: '',
    sort_order: 0,
    is_active: true,
});

const addInformation = () => {
    informationForm.post(route('services.information.store', props.service.id), {
        preserveScroll: true,
        onSuccess: () => {
            informationForm.reset();
            informationForm.is_active = true;
            informationForm.sort_order = 0;
        },
    });
};

const deleteInformation = (item) => {
    if (! confirm('Odstrániť túto informáciu?')) {
        return;
    }

    router.delete(route('services.information.destroy', [props.service.id, item.id]), {
        preserveScroll: true,
    });
};

const necessityForm = useForm({
    text: '',
    sort_order: 0,
    is_active: true,
});

const addNecessity = () => {
    necessityForm.post(route('services.necessities.store', props.service.id), {
        preserveScroll: true,
        onSuccess: () => {
            necessityForm.reset();
            necessityForm.is_active = true;
            necessityForm.sort_order = 0;
        },
    });
};

const deleteNecessity = (item) => {
    if (! confirm('Odstrániť túto položku?')) {
        return;
    }

    router.delete(route('services.necessities.destroy', [props.service.id, item.id]), {
        preserveScroll: true,
    });
};

const stepForm = useForm({
    number: null,
    title: '',
    text: '',
    sort_order: 0,
    is_active: true,
});

const addStep = () => {
    stepForm.post(route('services.steps.store', props.service.id), {
        preserveScroll: true,
        onSuccess: () => {
            stepForm.reset();
            stepForm.is_active = true;
            stepForm.sort_order = 0;
        },
    });
};

const deleteStep = (step) => {
    if (! confirm('Odstrániť tento krok?')) {
        return;
    }

    router.delete(route('services.steps.destroy', [props.service.id, step.id]), {
        preserveScroll: true,
    });
};

const tagForm = useForm({
    name: '',
    sort_order: 0,
});

const addTag = () => {
    tagForm.post(route('services.tags.store', props.service.id), {
        preserveScroll: true,
        onSuccess: () => {
            tagForm.reset();
            tagForm.sort_order = 0;
        },
    });
};

const deleteTag = (tag) => {
    if (! confirm(`Odstrániť tag ${tag.name}?`)) {
        return;
    }

    router.delete(route('services.tags.destroy', [props.service.id, tag.id]), {
        preserveScroll: true,
    });
};

const fileForm = useForm({
    label: '',
    file: null,
    sort_order: 0,
    is_active: true,
});

const handleFile = (event) => {
    fileForm.file = event.target.files[0] ?? null;
};

const addFile = () => {
    fileForm.post(route('services.files.store', props.service.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            fileForm.reset();
            fileForm.is_active = true;
            fileForm.sort_order = 0;
        },
    });
};

const deleteFile = (file) => {
    if (! confirm(`Odstrániť súbor ${file.original_name}?`)) {
        return;
    }

    router.delete(route('services.files.destroy', [props.service.id, file.id]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Upraviť službu
        </h1>

        <form class="max-w-4xl space-y-6" @submit.prevent="submit">
            <div class="rounded-lg border bg-white p-5">
                <h2 class="mb-4 text-lg font-semibold">
                    Základné údaje
                </h2>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Kategória</label>

                        <Select
                            v-model="form.category_id"
                            :options="categories"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Vyber kategóriu"
                            class="w-full"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Názov</label>

                        <InputText v-model="form.name" class="w-full" />

                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Slug</label>

                        <InputText v-model="form.slug" class="w-full" />

                        <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Ikona</label>

                        <InputText v-model="form.icon" class="w-full" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Trvanie v minútach</label>

                        <InputNumber
                            v-model="form.duration_minutes"
                            class="w-full"
                            inputClass="w-full"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Poradie</label>

                        <InputNumber
                            v-model="form.sort_order"
                            class="w-full"
                            inputClass="w-full"
                        />
                    </div>
                </div>

                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium">Krátky popis</label>

                    <InputText v-model="form.short_description" class="w-full" />
                </div>

                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium">Dlhý popis</label>

                    <Textarea v-model="form.description" class="w-full" rows="6" />
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <Checkbox v-model="form.is_active" binary inputId="service_is_active" />
                    <label for="service_is_active">Aktívna služba</label>
                </div>
            </div>

            <Button
                type="submit"
                label="Uložiť službu"
                icon="pi pi-save"
                :loading="form.processing"
            />
        </form>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-xl font-semibold">
                Informácie
            </h2>

            <form class="mb-6 grid gap-4 md:grid-cols-[1fr_150px_auto_auto]" @submit.prevent="addInformation">
                <Textarea v-model="informationForm.text" rows="2" placeholder="Text informácie" />
                <InputNumber v-model="informationForm.sort_order" inputClass="w-full" />
                <div class="flex items-center gap-2">
                    <Checkbox v-model="informationForm.is_active" binary inputId="info_active" />
                    <label for="info_active">Aktívne</label>
                </div>
                <Button type="submit" label="Pridať" />
            </form>

            <DataTable :value="service.information ?? []">
                <Column field="text" header="Text" />
                <Column field="sort_order" header="Poradie" />
                <Column header="Aktívne">
                    <template #body="{ data }">
                        {{ data.is_active ? 'Áno' : 'Nie' }}
                    </template>
                </Column>
                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteInformation(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-xl font-semibold">
                Čo potrebujete
            </h2>

            <form class="mb-6 grid gap-4 md:grid-cols-[1fr_150px_auto_auto]" @submit.prevent="addNecessity">
                <Textarea v-model="necessityForm.text" rows="2" placeholder="Napr. kartička poistenca" />
                <InputNumber v-model="necessityForm.sort_order" inputClass="w-full" />
                <div class="flex items-center gap-2">
                    <Checkbox v-model="necessityForm.is_active" binary inputId="necessity_active" />
                    <label for="necessity_active">Aktívne</label>
                </div>
                <Button type="submit" label="Pridať" />
            </form>

            <DataTable :value="service.necessities ?? []">
                <Column field="text" header="Text" />
                <Column field="sort_order" header="Poradie" />
                <Column header="Aktívne">
                    <template #body="{ data }">
                        {{ data.is_active ? 'Áno' : 'Nie' }}
                    </template>
                </Column>
                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteNecessity(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-xl font-semibold">
                Postup / kroky
            </h2>

            <form class="mb-6 space-y-4" @submit.prevent="addStep">
                <div class="grid gap-4 md:grid-cols-3">
                    <InputNumber v-model="stepForm.number" placeholder="Číslo kroku" inputClass="w-full" />
                    <InputText v-model="stepForm.title" placeholder="Názov kroku" />
                    <InputNumber v-model="stepForm.sort_order" placeholder="Poradie" inputClass="w-full" />
                </div>

                <Textarea v-model="stepForm.text" rows="3" placeholder="Popis kroku" />

                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="stepForm.is_active" binary inputId="step_active" />
                        <label for="step_active">Aktívne</label>
                    </div>

                    <Button type="submit" label="Pridať krok" />
                </div>
            </form>

            <DataTable :value="service.steps ?? []">
                <Column field="number" header="Číslo" />
                <Column field="title" header="Názov" />
                <Column field="text" header="Text" />
                <Column field="sort_order" header="Poradie" />
                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteStep(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-xl font-semibold">
                Tagy
            </h2>

            <form class="mb-6 grid gap-4 md:grid-cols-[1fr_150px_auto]" @submit.prevent="addTag">
                <InputText v-model="tagForm.name" placeholder="Názov tagu" />
                <InputNumber v-model="tagForm.sort_order" inputClass="w-full" />
                <Button type="submit" label="Pridať" />
            </form>

            <DataTable :value="service.tags ?? []">
                <Column field="name" header="Názov" />
                <Column field="slug" header="Slug" />
                <Column field="sort_order" header="Poradie" />
                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteTag(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-xl font-semibold">
                Dokumenty
            </h2>

            <form class="mb-6 grid gap-4 md:grid-cols-[1fr_1fr_150px_auto_auto]" @submit.prevent="addFile">
                <InputText v-model="fileForm.label" placeholder="Názov dokumentu" />

                <input
                    type="file"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                    @change="handleFile"
                />

                <InputNumber v-model="fileForm.sort_order" inputClass="w-full" />

                <div class="flex items-center gap-2">
                    <Checkbox v-model="fileForm.is_active" binary inputId="file_active" />
                    <label for="file_active">Aktívne</label>
                </div>

                <Button type="submit" label="Nahrať" />
            </form>

            <DataTable :value="service.files ?? []">
                <Column field="label" header="Názov" />
                <Column field="original_name" header="Súbor" />
                <Column field="sort_order" header="Poradie" />
                <Column header="Odkaz">
                    <template #body="{ data }">
                        <a
                            :href="`/storage/${data.file_path}`"
                            target="_blank"
                            class="text-blue-600 underline"
                        >
                            Otvoriť
                        </a>
                    </template>
                </Column>
                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteFile(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>
    </AdminLayout>
</template>