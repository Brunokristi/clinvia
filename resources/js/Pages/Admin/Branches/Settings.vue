<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import { computed, ref } from 'vue';

import Edit from './Settings/Edit.vue';
import Contacts from './Settings/Contacts.vue';
import Patients from './Settings/Patients.vue';
import OpeningHours from './Settings/OpeningHours.vue';
import Employees from './Settings/Employees.vue';
import Services from './Settings/Services.vue';
import Users from './Settings/Users.vue';
import PublicSite from './Settings/Tools.vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        default: null,
    },
    companies: {
        type: Array,
        default: () => [],
    },
    activeTab: {
        type: String,
        default: 'info',
    },
    categories: {
        type: Array,
        default: () => [],
    },
    availableUsers: {
        type: Array,
        default: () => [],
    },
    templates: {
        type: Array,
        default: () => [],
    },
    insuranceCompanies: {
        type: Object,
        default: () => ({}),
    },
    contacts: {
        type: Array,
        default: () => [],
    },
    openingHours: {
        type: Array,
        default: () => [],
    },
    employees: {
        type: Array,
        default: () => [],
    },
    services: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
    publicSite: {
        type: Object,
        default: null,
    },
});

const activeTab = ref(props.activeTab ?? 'info');

const branchName = computed(() => props.branch.name || 'Pobočka');

const settingsTabs = computed(() => [
    {
        value: 'info',
        label: 'Info',
        description: 'Základné informácie a podrobnosti o tejto pobočke.',
        stats: null,
        component: Edit,
        componentProps: {
            branch: props.branch,
            company: props.branch.company ?? props.company,
            companies: props.companies,
        },
    },
        {
        value: 'publicSite',
        label: 'Nástroje',
        description: 'Nastavte nástroje tejto pobočky.',
        stats: props.branch.public_site || props.publicSite ? 'Aktívna' : 'Nie je aktívna',
        component: PublicSite,
        componentProps: {
            branch: props.branch,
            templates: props.templates,
            insuranceCompanies: props.insuranceCompanies,
        },
    },
    {
        value: 'contacts',
        label: 'Kontakty',
        description: 'Adresy, telefóny a ďalšie kontaktné údaje pobočky.',
        stats: `${props.branch.contacts_count ?? props.contacts.length ?? 0} kontaktov`,
        component: Contacts,
        componentProps: {
            branch: props.branch,
            contacts: props.contacts,
        },
    },
    {
        value: 'patients',
        label: 'Pacienti',
        description: 'Správa pacientov priradených k tejto pobočke.',
        stats: `${props.branch.patients_count ?? props.branch.patients?.length ?? 0} pacientov`,
        component: Patients,
        componentProps: {
            branch: props.branch,
        },
    },
    {
        value: 'openingHours',
        label: 'Otváracie hodiny',
        description: 'Nastavte otváracie hodiny pre túto pobočku.',
        stats: `${props.branch.opening_hours_count ?? props.openingHours.length ?? 0} dní`,
        component: OpeningHours,
        componentProps: {
            branch: props.branch,
            openingHours: props.openingHours,
        },
    },
    {
        value: 'employees',
        label: 'Zamestnanci',
        description: 'Spravujte zamestnancov priradených k tejto pobočke.',
        stats: `${props.branch.employees_count ?? props.employees.length ?? 0} zamestnancov`,
        component: Employees,
        componentProps: {
            branch: props.branch,
            employees: props.employees,
        },
    },
    {
        value: 'services',
        label: 'Služby',
        description: 'Spravujte služby a ich nastavenia pre túto pobočku.',
        stats: `${props.branch.services_count ?? props.services.length ?? 0} služieb`,
        component: Services,
        componentProps: {
            branch: props.branch,
            categories: props.categories,
        },
    },
    {
        value: 'users',
        label: 'Používatelia',
        description: 'Spravujte používateľov a pozvánky pre túto pobočku.',
        stats: `${props.branch.users_count ?? props.users.length ?? 0} používateľov`,
        component: Users,
        componentProps: {
            branch: props.branch,
            availableUsers: props.availableUsers,
        },
    },
]);
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <Tabs
                v-model:value="activeTab"
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
                <TabList class="flex flex-wrap gap-2">
                    <Tab
                        v-for="tab in settingsTabs"
                        :key="tab.value"
                        :value="tab.value"
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
                        {{ tab.label }}
                    </Tab>
                </TabList>

                <TabPanels>
                    <TabPanel
                        v-for="tab in settingsTabs"
                        :key="tab.value"
                        :value="tab.value"
                    >
                        <div class="space-y-4">
                            <component
                                :is="tab.component"
                                v-bind="tab.componentProps"
                            />
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </AdminLayout>
</template>