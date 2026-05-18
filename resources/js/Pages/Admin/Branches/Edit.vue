<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    branch: Object,
    companies: Array,
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
    form.put(route('branches.update', props.branch.id));
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
                        <label class="mb-1 block text-sm font-medium">Firma</label>
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
                        <label class="mb-1 block text-sm font-medium">Typ pobočky</label>
                        <Select
                            v-model="form.type"
                            :options="branchTypes"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Vyber typ"
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
                </div>

                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium">Popis</label>
                    <Textarea v-model="form.description" class="w-full" rows="5" />
                </div>
            </div>

            <div class="rounded-lg border bg-white p-5">
                <h2 class="mb-4 text-lg font-semibold">
                    Adresa
                </h2>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Adresa 1</label>
                        <InputText v-model="form.address_line_1" class="w-full" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Adresa 2</label>
                        <InputText v-model="form.address_line_2" class="w-full" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Mesto</label>
                        <InputText v-model="form.city" class="w-full" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">PSČ</label>
                        <InputText v-model="form.postal_code" class="w-full" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Krajina</label>
                        <InputText v-model="form.country" class="w-full" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Web</label>
                        <InputText v-model="form.website" class="w-full" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Latitude</label>
                        <InputNumber
                            v-model="form.latitude"
                            class="w-full"
                            inputClass="w-full"
                            :minFractionDigits="0"
                            :maxFractionDigits="7"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Longitude</label>
                        <InputNumber
                            v-model="form.longitude"
                            class="w-full"
                            inputClass="w-full"
                            :minFractionDigits="0"
                            :maxFractionDigits="7"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-5">
                <h2 class="mb-4 text-lg font-semibold">
                    Nastavenia
                </h2>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Poradie</label>
                        <InputNumber v-model="form.sort_order" class="w-full" inputClass="w-full" />
                    </div>

                    <div class="flex items-center gap-2 pt-7">
                        <Checkbox v-model="form.is_active" binary inputId="is_active_edit" />
                        <label for="is_active_edit">Aktívna pobočka</label>
                    </div>
                </div>
            </div>

            <Button type="submit" label="Uložiť" :loading="form.processing" />
        </form>
    </AdminLayout>
</template>