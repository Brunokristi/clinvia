<script setup>
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';

const page = usePage();
const toast = useToast();

const user = page.props.auth.user;

const profileForm = useForm({
    first_name: user.first_name ?? '',
    last_name: user.last_name ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submitProfile = () => {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Úspech',
                detail: 'Profil bol úspešne upravený.',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Chyba',
                detail: 'Nepodarilo sa upraviť profil.',
                life: 3000,
            });
        },
    });
};

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset('current_password', 'password', 'password_confirmation');

            toast.add({
                severity: 'success',
                summary: 'Úspech',
                detail: 'Heslo bolo úspešne zmenené.',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Chyba',
                detail: 'Nepodarilo sa zmeniť heslo.',
                life: 3000,
            });
        },
    });
};
</script>

<template>
    <AdminLayout>
        <Head title="Nastavenia" />

        <div>
            <Tabs
                value="profile"
                :pt="{
                    root: {
                        class: '!w-full',
                    },
                    tablist: {
                        class: '!border-none !bg-transparent',
                    },
                    activeBar: {
                        class: '!hidden',
                    },
                    tabpanels: {
                        class: '!bg-transparent !p-0',
                    },
                }"
            >
                <TabList>
                    <Tab
                        value="profile"
                        :pt="{
                            root: {
                                class: [
                                    '!rounded-md !border-0 !px-5 !py-3',
                                    '!text-normal !font-semibold',
                                    '!text-accent',
                                    'data-[p-active=true]:!bg-accent data-[p-active=true]:!text-white',
                                    'hover:!bg-soft hover:!text-accent',
                                    'focus:!shadow-none focus:!outline-none focus:!ring-0',
                                ],
                            },
                        }"
                    >
                        Profil
                    </Tab>

                    <Tab
                        value="password"
                        :pt="{
                            root: {
                                class: [
                                    '!rounded-md !border-0 !px-5 !py-3',
                                    '!text-normal !font-semibold',
                                    '!text-accent',
                                    'data-[p-active=true]:!bg-accent data-[p-active=true]:!text-white',
                                    'hover:!bg-soft hover:!text-accent',
                                    'focus:!shadow-none focus:!outline-none focus:!ring-0',
                                ],
                            },
                        }"
                    >
                        Heslo
                    </Tab>
                </TabList>

                <TabPanels>
                    <TabPanel
                        value="profile"
                    >
                        <form @submit.prevent="submitProfile">
                            <FormPage
                                submit-label="Uložiť zmeny"
                                :loading="profileForm.processing"
                            >
                                <FormSection
                                    title="Profil"
                                    description="Základné údaje používateľa."
                                    columns="md:grid-cols-2"
                                >
                                    <FormField
                                        label="Meno"
                                        for="first_name"
                                        required
                                        :error="profileForm.errors.first_name"
                                    >
                                        <InputText
                                            id="first_name"
                                            v-model="profileForm.first_name"
                                            class="w-full"
                                            autocomplete="given-name"
                                            :invalid="Boolean(profileForm.errors.first_name)"
                                        />
                                    </FormField>

                                    <FormField
                                        label="Priezvisko"
                                        for="last_name"
                                        required
                                        :error="profileForm.errors.last_name"
                                    >
                                        <InputText
                                            id="last_name"
                                            v-model="profileForm.last_name"
                                            class="w-full"
                                            autocomplete="family-name"
                                            :invalid="Boolean(profileForm.errors.last_name)"
                                        />
                                    </FormField>

                                    <FormField
                                        label="Email"
                                        for="email"
                                        span="md:col-span-2"
                                    >
                                        <div class="rounded-md bg-soft px-4 py-2 text-normal text-accent">
                                            {{ user.email }}
                                        </div>
                                    </FormField>
                                </FormSection>
                            </FormPage>
                        </form>
                    </TabPanel>

                    <TabPanel
                        value="password"
                    >
                        <form @submit.prevent="submitPassword">
                            <FormPage
                                submit-label="Zmeniť heslo"
                                :loading="passwordForm.processing"
                            >
                                <FormSection
                                    title="Heslo"
                                    description="Zmena hesla."
                                    columns="md:grid-cols-2"
                                >
                                    <FormField
                                        label="Aktuálne heslo"
                                        for="current_password"
                                        required
                                        :error="passwordForm.errors.current_password"
                                        span="md:col-span-2"
                                    >
                                        <Password
                                            id="current_password"
                                            v-model="passwordForm.current_password"
                                            class="w-full"
                                            input-class="w-full"
                                            :feedback="false"
                                            toggle-mask
                                            autocomplete="current-password"
                                            :invalid="Boolean(passwordForm.errors.current_password)"
                                        />
                                    </FormField>

                                    <FormField
                                        label="Nové heslo"
                                        for="password"
                                        required
                                        :error="passwordForm.errors.password"
                                    >
                                        <Password
                                            id="password"
                                            v-model="passwordForm.password"
                                            class="w-full"
                                            input-class="w-full"
                                            :feedback="false"
                                            toggle-mask
                                            autocomplete="new-password"
                                            :invalid="Boolean(passwordForm.errors.password)"
                                        />
                                    </FormField>

                                    <FormField
                                        label="Potvrdenie hesla"
                                        for="password_confirmation"
                                        required
                                        :error="passwordForm.errors.password_confirmation"
                                    >
                                        <Password
                                            id="password_confirmation"
                                            v-model="passwordForm.password_confirmation"
                                            class="w-full"
                                            input-class="w-full"
                                            :feedback="false"
                                            toggle-mask
                                            autocomplete="new-password"
                                            :invalid="Boolean(passwordForm.errors.password_confirmation)"
                                        />
                                    </FormField>
                                </FormSection>
                            </FormPage>
                        </form>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </AdminLayout>
</template>