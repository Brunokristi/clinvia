<script setup>
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    templates: {
        type: Array,
        default: () => [],
    },
});

const publicSite = props.branch.public_site ?? {};

const form = useForm({
    is_enabled: publicSite.is_enabled ?? false,
    template: publicSite.template ?? 'default',
    custom_domain: publicSite.custom_domain ?? '',
    primary_color: publicSite.primary_color ?? '',
    secondary_color: publicSite.secondary_color ?? '',
    logo_path: publicSite.logo_path ?? '',
    meta_title: publicSite.meta_title ?? '',
    meta_description: publicSite.meta_description ?? '',
});

const submit = () => {
    form.put(route('branches.public-site.update', props.branch.id), {
        preserveScroll: true,
    });
};

const publicUrl = `/p/${props.branch.slug}`;
</script>

<template>
    <AdminLayout>
        <Head title="Verejná stránka" />

        <form
            @submit.prevent="submit"
        >
            <FormPage
                submit-label="Uložiť nastavenia"
                :loading="form.processing"
            >
                <FormSection
                    title="Verejná stránka"
                    :description="`Nastavenia stránky pre pobočku ${branch.name}.`"
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Zverejniť stránku"
                        for="is_enabled"
                        :error="form.errors.is_enabled"
                        span="md:col-span-2"
                    >
                        <div class="flex items-center gap-3 mt-2">
                            <Checkbox
                                id="is_enabled"
                                v-model="form.is_enabled"
                                binary
                                :invalid="Boolean(form.errors.is_enabled)"
                            />

                                <p class="text-normal text-accent">
                                    Verejná stránka je aktívna
                                </p>
                        </div>
                    </FormField>

                    <FormField
                        label="Šablóna"
                        for="template"
                        required
                        :error="form.errors.template"
                    >
                        <Select
                            id="template"
                            v-model="form.template"
                            :options="templates"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                            placeholder="Vyberte šablónu"
                            :invalid="Boolean(form.errors.template)"
                        />
                    </FormField>

                    <FormField
                        label="Verejná URL"
                        for="public_url"
                    >
                        <div class="rounded-md bg-soft px-4 py-2 text-normal text-accent">
                            {{ publicUrl }}
                        </div>
                    </FormField>

                    <FormField
                        label="Vlastná doména"
                        for="custom_domain"
                        :error="form.errors.custom_domain"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="custom_domain"
                            v-model="form.custom_domain"
                            class="w-full"
                            placeholder="napr. www.klinika.sk"
                            :invalid="Boolean(form.errors.custom_domain)"
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    title="Vzhľad"
                    description="Základné nastavenia vzhľadu generovanej stránky."
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Primárna farba"
                        for="primary_color"
                        :error="form.errors.primary_color"
                    >
                        <InputText
                            id="primary_color"
                            v-model="form.primary_color"
                            class="w-full"
                            placeholder="#7c3aed"
                            :invalid="Boolean(form.errors.primary_color)"
                        />
                    </FormField>

                    <FormField
                        label="Sekundárna farba"
                        for="secondary_color"
                        :error="form.errors.secondary_color"
                    >
                        <InputText
                            id="secondary_color"
                            v-model="form.secondary_color"
                            class="w-full"
                            placeholder="#f5f3ff"
                            :invalid="Boolean(form.errors.secondary_color)"
                        />
                    </FormField>

                    <FormField
                        label="Logo"
                        for="logo_path"
                        :error="form.errors.logo_path"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="logo_path"
                            v-model="form.logo_path"
                            class="w-full"
                            placeholder="Cesta k logu alebo upload neskôr"
                            :invalid="Boolean(form.errors.logo_path)"
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    title="SEO"
                    description="Texty pre názov stránky a popis vo vyhľadávačoch."
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Meta title"
                        for="meta_title"
                        :error="form.errors.meta_title"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="meta_title"
                            v-model="form.meta_title"
                            class="w-full"
                            placeholder="Klinická psychológia Lučenec"
                            :invalid="Boolean(form.errors.meta_title)"
                        />
                    </FormField>

                    <FormField
                        label="Meta description"
                        for="meta_description"
                        :error="form.errors.meta_description"
                        span="md:col-span-2"
                    >
                        <Textarea
                            id="meta_description"
                            v-model="form.meta_description"
                            class="w-full"
                            rows="3"
                            placeholder="Krátky popis verejnej stránky..."
                            :invalid="Boolean(form.errors.meta_description)"
                        />
                    </FormField>
                </FormSection>
            </FormPage>
        </form>
    </AdminLayout>
</template>