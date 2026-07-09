<script setup>
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    insuranceCompanies: {
        type: Object,
        default: () => ({}),
    },
    templates: {
        type: Array,
        default: () => [
            {
                label: 'Predvolená',
                value: 'default',
            },
        ],
    },
});

const publicSite = props.branch.public_site ?? {};
const bookingSettings = props.branch.booking_settings ?? {};
const notificationSettings = props.branch.notification_settings ?? {};
const contractedInsuranceCompanies = props.branch.contracted_insurance_companies ?? [];

const insuranceCompanyOptions = computed(() => {
    return Object.entries(props.insuranceCompanies ?? {}).map(([key, item]) => ({
        key,
        label: item.label ?? key,
        full_name: item.full_name ?? item.label ?? key,
    }));
});

const bookingModeOptions = [
    {
        label: 'Len požiadavky',
        value: 'requests_only',
    },
    {
        label: 'Len overení pacienti',
        value: 'verified_patients_only',
    },
    {
        label: 'Len administrátor',
        value: 'admin_only',
    },
];

const form = useForm({
    public_site: {
        is_enabled: publicSite.is_enabled ?? false,
        template: publicSite.template ?? 'default',
        custom_domain: publicSite.custom_domain ?? '',
        primary_color: publicSite.primary_color ?? '',
        secondary_color: publicSite.secondary_color ?? '',
        logo_path: publicSite.logo_path ?? '',
        meta_title: publicSite.meta_title ?? '',
        meta_description: publicSite.meta_description ?? '',
    },

    booking: {
        is_enabled: bookingSettings.is_enabled ?? false,
        allow_service_selection: bookingSettings.allow_service_selection ?? true,
        allow_appointment_requests: bookingSettings.allow_appointment_requests ?? true,
        booking_mode: bookingSettings.booking_mode ?? 'requests_only',
        intro_text: bookingSettings.intro_text ?? '',
        success_message: bookingSettings.success_message ?? '',
    },

    contracted_insurance_companies: Array.isArray(contractedInsuranceCompanies)
        ? contractedInsuranceCompanies
        : [],
    show_other_branches_in_footer: Boolean(props.branch.show_other_branches_in_footer ?? false),

    notifications: {
        is_enabled: notificationSettings.is_enabled ?? false,
        notification_emails: notificationSettings.notification_emails?.length
            ? notificationSettings.notification_emails
            : [''],
        notify_new_appointment_request: notificationSettings.notify_new_appointment_request ?? true,
        notify_new_booking: notificationSettings.notify_new_booking ?? true,
        notify_new_contact_form: notificationSettings.notify_new_contact_form ?? true,
    },
});

const addNotificationEmail = () => {
    form.notifications.notification_emails.push('');
};

watch(
    () => form.booking.is_enabled,
    (isEnabled) => {
        if (! isEnabled) {
            form.notifications.notify_new_appointment_request = false;
            form.notifications.notify_new_booking = false;
        }
    },
    { immediate: true },
);

const removeNotificationEmail = (index) => {
    form.notifications.notification_emails.splice(index, 1);

    if (!form.notifications.notification_emails.length) {
        form.notifications.notification_emails.push('');
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        notifications: {
            ...data.notifications,
            notification_emails: data.notifications.notification_emails
                .map((email) => email.trim())
                .filter(Boolean),
        },
    })).put(route('branches.settings.update', {
        branch: props.branch.id,
    }), {
        preserveScroll: true,
    });
};

const publicUrl = `/p/${props.branch.slug}`;
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <FormPage
            submit-label="Uložiť nastavenia"
            :loading="form.processing"
        >
            <FormSection
                title="Verejná stránka"
                :description="`Nastavenia verejnej stránky pre pobočku ${branch.name}.`"
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Zverejniť stránku"
                    for="public_site_is_enabled"
                    :error="form.errors['public_site.is_enabled']"
                    span="md:col-span-2"
                >
                    <div class="mt-2 flex items-center gap-3">
                        <Checkbox
                            id="public_site_is_enabled"
                            v-model="form.public_site.is_enabled"
                            binary
                            :invalid="Boolean(form.errors['public_site.is_enabled'])"
                        />

                        <p class="text-normal text-accent">
                            Verejná stránka je aktívna
                        </p>
                    </div>
                </FormField>

                <template v-if="form.public_site.is_enabled">
                    <FormField
                        label="Šablóna"
                        for="template"
                        required
                        :error="form.errors['public_site.template']"
                    >
                        <Select
                            id="template"
                            v-model="form.public_site.template"
                            :options="templates"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                            placeholder="Vyberte šablónu"
                            :invalid="Boolean(form.errors['public_site.template'])"
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
                        :error="form.errors['public_site.custom_domain']"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="custom_domain"
                            v-model="form.public_site.custom_domain"
                            class="w-full"
                            placeholder="napr. www.klinika.sk"
                            :invalid="Boolean(form.errors['public_site.custom_domain'])"
                        />
                    </FormField>

                    <FormField
                        label="Primárna farba"
                        for="primary_color"
                        :error="form.errors['public_site.primary_color']"
                    >
                        <InputText
                            id="primary_color"
                            v-model="form.public_site.primary_color"
                            class="w-full"
                            placeholder="#7c3aed"
                            :invalid="Boolean(form.errors['public_site.primary_color'])"
                        />
                    </FormField>

                    <FormField
                        label="Sekundárna farba"
                        for="secondary_color"
                        :error="form.errors['public_site.secondary_color']"
                    >
                        <InputText
                            id="secondary_color"
                            v-model="form.public_site.secondary_color"
                            class="w-full"
                            placeholder="#f5f3ff"
                            :invalid="Boolean(form.errors['public_site.secondary_color'])"
                        />
                    </FormField>

                    <FormField
                        label="Logo"
                        for="logo_path"
                        :error="form.errors['public_site.logo_path']"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="logo_path"
                            v-model="form.public_site.logo_path"
                            class="w-full"
                            placeholder="Cesta k logu alebo upload neskôr"
                            :invalid="Boolean(form.errors['public_site.logo_path'])"
                        />
                    </FormField>

                    <FormField
                        label="Meta title"
                        for="meta_title"
                        :error="form.errors['public_site.meta_title']"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="meta_title"
                            v-model="form.public_site.meta_title"
                            class="w-full"
                            placeholder="Klinická psychológia Lučenec"
                            :invalid="Boolean(form.errors['public_site.meta_title'])"
                        />
                    </FormField>

                    <FormField
                        label="Meta description"
                        for="meta_description"
                        :error="form.errors['public_site.meta_description']"
                        span="md:col-span-2"
                    >
                        <Textarea
                            id="meta_description"
                            v-model="form.public_site.meta_description"
                            class="w-full"
                            rows="3"
                            placeholder="Krátky popis verejnej stránky..."
                            :invalid="Boolean(form.errors['public_site.meta_description'])"
                        />
                    </FormField>
                </template>
            </FormSection>

            <FormSection
                title="Rezervačné služby"
                description="Nastavenia toho, či môžu pacienti posielať žiadosti o termín cez verejnú stránku."
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Povoliť rezervácie"
                    for="booking_is_enabled"
                    :error="form.errors['booking.is_enabled']"
                    span="md:col-span-2"
                >
                    <div class="mt-2 flex items-center gap-3">
                        <Checkbox
                            id="booking_is_enabled"
                            v-model="form.booking.is_enabled"
                            binary
                            :invalid="Boolean(form.errors['booking.is_enabled'])"
                        />

                        <p class="text-normal text-accent">
                            Rezervácie online sú povolené
                        </p>
                    </div>
                </FormField>

                <FormField
                    v-if="form.booking.is_enabled"
                    label="Režim verejného objednávania"
                    for="booking_mode"
                    :error="form.errors['booking.booking_mode']"
                    span="md:col-span-2"
                >
                    <Select
                        id="booking_mode"
                        v-model="form.booking.booking_mode"
                        :options="bookingModeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                        :invalid="Boolean(form.errors['booking.booking_mode'])"
                    />

                    <p class="mt-2 text-sm text-accent">
                        Tento režim riadi, či sa verejné odoslanie vytvorí ako požiadavka alebo môže potvrdiť termín okamžite.
                    </p>
                </FormField>

                <FormField
                    v-if="form.booking.is_enabled"
                    label="Zobrazovať ostatné pobočky vo footeri"
                    for="show_other_branches_in_footer"
                    :error="form.errors.show_other_branches_in_footer"
                    span="md:col-span-2"
                >
                    <div class="mt-2 flex items-center gap-3">
                        <Checkbox
                            id="show_other_branches_in_footer"
                            v-model="form.show_other_branches_in_footer"
                            binary
                            :invalid="Boolean(form.errors.show_other_branches_in_footer)"
                        />

                        <p class="text-normal text-accent">
                            Vo verejnom footeri sa zobrazia odkazy na ďalšie aktívne pobočky spoločnosti.
                        </p>
                    </div>
                </FormField>

                <FormField
                    v-if="form.booking.is_enabled"
                    label="Zmluvné poisťovne"
                    for="contracted_insurance_companies"
                    :error="form.errors.contracted_insurance_companies"
                    span="md:col-span-2"
                >
                    <p class="mb-3 text-sm text-accent">
                        Vyberte zdravotné poisťovne, s ktorými má táto pobočka uzatvorenú zmluvu.
                    </p>

                    <div class="space-y-3">
                        <label
                            v-for="option in insuranceCompanyOptions"
                            :key="option.key"
                            class="flex items-start gap-3"
                        >
                            <Checkbox
                                v-model="form.contracted_insurance_companies"
                                :input-id="`insurance_${option.key}`"
                                :value="option.key"
                                :invalid="Boolean(form.errors.contracted_insurance_companies)"
                            />

                            <span class="text-normal text-dark">
                                <span class="font-medium">{{ option.label }}</span>
                                <span class="block text-sm text-accent">{{ option.full_name }}</span>
                            </span>
                        </label>
                    </div>

                    <p
                        v-if="form.errors.contracted_insurance_companies"
                        class="mt-2 text-small text-red-500"
                    >
                        {{ form.errors.contracted_insurance_companies }}
                    </p>
                </FormField>
            </FormSection>

            <FormSection
                title="E-mailové notifikácie"
                description="Nastavenia e-mailov, ktoré sa odosielajú pri nových žiadostiach, rezerváciách a kontaktných formulároch."
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Povoliť notifikácie"
                    for="notifications_is_enabled"
                    :error="form.errors['notifications.is_enabled']"
                    span="md:col-span-2"
                >
                    <div class="mt-2 flex items-center gap-3">
                        <Checkbox
                            id="notifications_is_enabled"
                            v-model="form.notifications.is_enabled"
                            binary
                            :invalid="Boolean(form.errors['notifications.is_enabled'])"
                        />

                        <p class="text-normal text-accent">
                            E-mailové notifikácie sú aktívne
                        </p>
                    </div>
                </FormField>

                <template v-if="form.notifications.is_enabled">
                    <FormField
                        label="E-maily pre notifikácie"
                        for="notification_emails"
                        required
                        :error="form.errors['notifications.notification_emails']"
                        span="md:col-span-2"
                    >
                        <div class="space-y-3">
                            <div
                                v-for="(email, index) in form.notifications.notification_emails"
                                :key="index"
                                class="flex gap-2"
                            >
                                <InputText
                                    :id="`notification_email_${index}`"
                                    v-model="form.notifications.notification_emails[index]"
                                    class="w-full"
                                    placeholder="napr. recepcia@klinika.sk"
                                    :invalid="
                                        Boolean(form.errors['notifications.notification_emails'])
                                        || Boolean(form.errors[`notifications.notification_emails.${index}`])
                                    "
                                />

                                <Button
                                    type="button"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    :disabled="form.notifications.notification_emails.length === 1"
                                    @click="removeNotificationEmail(index)"
                                />
                            </div>

                            <Button
                                type="button"
                                label="Pridať e-mail"
                                outlined
                                @click="addNotificationEmail"
                            />

                            <p
                                v-if="form.errors['notifications.notification_emails']"
                                class="text-small text-red-500"
                            >
                                {{ form.errors['notifications.notification_emails'] }}
                            </p>
                        </div>
                    </FormField>

                    <FormField
                        v-if="form.booking.is_enabled"
                        label="Nové žiadosti"
                        for="notify_new_appointment_request"
                        :error="form.errors['notifications.notify_new_appointment_request']"
                        span="md:col-span-2"
                    >
                        <div class="mt-2 flex items-center gap-3">
                            <Checkbox
                                id="notify_new_appointment_request"
                                v-model="form.notifications.notify_new_appointment_request"
                                binary
                                :invalid="Boolean(form.errors['notifications.notify_new_appointment_request'])"
                            />

                            <p class="text-normal text-accent">
                                Poslať e-mail pri novej žiadosti o termín
                            </p>
                        </div>
                    </FormField>

                    <FormField
                        v-if="form.booking.is_enabled"
                        label="Nové rezervácie"
                        for="notify_new_booking"
                        :error="form.errors['notifications.notify_new_booking']"
                        span="md:col-span-2"
                    >
                        <div class="mt-2 flex items-center gap-3">
                            <Checkbox
                                id="notify_new_booking"
                                v-model="form.notifications.notify_new_booking"
                                binary
                                :invalid="Boolean(form.errors['notifications.notify_new_booking'])"
                            />

                            <p class="text-normal text-accent">
                                Poslať e-mail pri vytvorení rezervácie
                            </p>
                        </div>
                    </FormField>

                    <FormField
                        label="Kontaktné formuláre"
                        for="notify_new_contact_form"
                        :error="form.errors['notifications.notify_new_contact_form']"
                        span="md:col-span-2"
                    >
                        <div class="mt-2 flex items-center gap-3">
                            <Checkbox
                                id="notify_new_contact_form"
                                v-model="form.notifications.notify_new_contact_form"
                                binary
                                :invalid="Boolean(form.errors['notifications.notify_new_contact_form'])"
                            />

                            <p class="text-normal text-accent">
                                Poslať e-mail pri odoslaní kontaktného formulára
                            </p>
                        </div>
                    </FormField>
                </template>
            </FormSection>
        </FormPage>
    </form>
</template>