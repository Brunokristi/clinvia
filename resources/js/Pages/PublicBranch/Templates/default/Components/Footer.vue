<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
});

const dayNames = {
    1: 'Pondelok',
    2: 'Utorok',
    3: 'Streda',
    4: 'Štvrtok',
    5: 'Piatok',
    6: 'Sobota',
    7: 'Nedeľa',
};

const links = computed(() => [
    {
        label: 'Domov',
        href: route('public.branch.home', props.branch.slug),
    },
    {
        label: 'Služby',
        href: route('public.branch.services', props.branch.slug),
    },
    {
        label: 'Kontakt',
        href: route('public.branch.contact', props.branch.slug),
    },
]);

const mainContacts = computed(() => {
    return props.branch.contacts ?? [];
});

const contactIcon = (type) => {
    return {
        email: 'pi pi-envelope',
        phone: 'pi pi-phone',
        booking_phone: 'pi pi-phone',
        website: 'pi pi-globe',
        facebook: 'pi pi-facebook',
        instagram: 'pi pi-instagram',
    }[type] ?? 'pi pi-info-circle';
};

const contactHref = (contact) => {
    if (contact.type === 'email') {
        return `mailto:${contact.value}`;
    }

    if (['phone', 'booking_phone'].includes(contact.type)) {
        return `tel:${contact.value.replace(/\s+/g, '')}`;
    }

    if (['website', 'facebook', 'instagram'].includes(contact.type)) {
        return contact.value;
    }

    return null;
};

const openingHoursLabel = (openingHour) => {
    if (openingHour.is_closed) {
        return 'Zatvorené';
    }

    if (!openingHour.intervals?.length) {
        return 'Neuvedené';
    }

    return openingHour.intervals
        .map((interval) => `${interval.opens_at.slice(0, 5)} – ${interval.closes_at.slice(0, 5)}`)
        .join(', ');
};

const companyName = computed(() => {
    return props.branch.company?.name ?? props.branch.company?.legal_name ?? null;
});

const companyIco = computed(() => {
    return props.branch.company?.ico
        ?? props.branch.company?.company_id_number
        ?? null;
});

const companyDic = computed(() => {
    return props.branch.company?.dic
        ?? props.branch.company?.tax_id
        ?? null;
});

const companyIcDph = computed(() => {
    return props.branch.company?.ic_dph
        ?? props.branch.company?.vat_id
        ?? null;
});

const fullAddress = computed(() => {
    return [
        props.branch.address?.line_1,
        props.branch.address?.line_2,
        [props.branch.address?.postal_code, props.branch.address?.city].filter(Boolean).join(' '),
        props.branch.address?.country,
    ].filter(Boolean);
});

const contractedInsuranceCompanies = computed(() => {
    return props.branch.contracted_insurance_companies ?? [];
});

const hasContractedInsuranceCompanies = computed(() => {
    return contractedInsuranceCompanies.value.length > 0;
});

const otherCompanyBranches = computed(() => {
    return props.branch.other_company_branches ?? [];
});

const hasOtherCompanyBranches = computed(() => {
    return Boolean(props.branch.show_other_branches_in_footer)
        && otherCompanyBranches.value.length > 0;
});

const hasFooterExtras = computed(() => {
    return hasContractedInsuranceCompanies.value || hasOtherCompanyBranches.value;
});

const otherBranchSecondaryLabel = (branchItem) => {
    return branchItem.city || branchItem.address_line_1 || null;
};
</script>

<template>
    <footer class="border-t border-accent bg-soft">
        <div
            class="mx-auto grid max-w-6xl"
            :class="hasFooterExtras ? 'lg:grid-cols-4' : 'lg:grid-cols-3'"
        >
            <div class="border-b border-accent px-6 py-8 lg:border-b-0 lg:border-r lg:py-10 lg:pl-6 lg:pr-8">
                <h2 class="text-normal font-semibold text-dark">
                    {{ branch.name }}
                </h2>

                <div
                    v-if="fullAddress.length"
                    class="mt-4 space-y-1 text-sm leading-6 text-accent"
                >
                    <p
                        v-for="line in fullAddress"
                        :key="line"
                    >
                        {{ line }}
                    </p>
                </div>

                <p
                    v-if="branch.description"
                    class="mt-4 line-clamp-4 text-sm leading-6 text-accent"
                >
                    {{ branch.description }}
                </p>
            </div>

            <div class="border-b border-accent px-6 py-8 lg:border-b-0 lg:border-r lg:px-8 lg:py-10">
                <h2 class="text-normal font-semibold text-dark">
                    Kontakt
                </h2>

                <div
                    v-if="mainContacts.length"
                    class="mt-4 space-y-3"
                >
                    <component
                        :is="contactHref(contact) ? 'a' : 'div'"
                        v-for="(contact, index) in mainContacts"
                        :key="index"
                        :href="contactHref(contact)"
                        class="flex items-start gap-3 text-sm text-accent transition hover:text-dark"
                    >
                        <i
                            :class="contactIcon(contact.type)"
                            class="mt-1 shrink-0 text-xs"
                        />

                        <span class="min-w-0 break-words">
                            {{ contact.value }}
                        </span>
                    </component>
                </div>

                <p
                    v-else
                    class="mt-4 text-sm text-accent"
                >
                    Kontaktné údaje zatiaľ nie sú uvedené.
                </p>
            </div>

            <div
                class="border-b border-accent px-6 py-8 lg:border-b-0 lg:py-10 lg:pl-8 lg:pr-6"
                :class="hasFooterExtras ? 'lg:border-r' : ''"
            >
                <h2 class="text-normal font-semibold text-dark">
                    Otváracie hodiny
                </h2>

                <div
                    v-if="branch.opening_hours?.length"
                    class="mt-4 space-y-2"
                >
                    <div
                        v-for="openingHour in branch.opening_hours"
                        :key="openingHour.day_of_week"
                        class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 text-sm"
                    >
                        <span class="min-w-0 text-dark">
                            {{ dayNames[openingHour.day_of_week] }}
                        </span>

                        <span class="text-right text-accent">
                            {{ openingHoursLabel(openingHour) }}
                        </span>
                    </div>
                </div>

                <p
                    v-else
                    class="mt-4 text-sm text-accent"
                >
                    Otváracie hodiny zatiaľ nie sú uvedené.
                </p>
            </div>

            <div
                v-if="hasContractedInsuranceCompanies || hasOtherCompanyBranches"
                class="px-6 py-8 lg:py-10 lg:pl-8 lg:pr-6"
            >
                <div
                    v-if="hasContractedInsuranceCompanies"
                    class="space-y-3"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Zmluvné poisťovne
                    </h2>

                    <p class="text-sm text-accent">
                        {{ contractedInsuranceCompanies.map((item) => item.label).join(' · ') }}
                    </p>
                </div>

                <div
                    v-if="hasOtherCompanyBranches"
                    class="space-y-3"
                    :class="hasContractedInsuranceCompanies ? 'mt-6' : ''"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Naše pobočky
                    </h2>

                    <ul class="space-y-2">
                        <li
                            v-for="otherBranch in otherCompanyBranches"
                            :key="otherBranch.id"
                        >
                            <Link
                                :href="otherBranch.href"
                                class="text-sm text-accent transition hover:text-dark"
                            >
                                <span class="block">{{ otherBranch.name }}</span>
                                <span
                                    v-if="otherBranchSecondaryLabel(otherBranch)"
                                    class="text-xs text-accent/80"
                                >
                                    {{ otherBranchSecondaryLabel(otherBranch) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="border-t border-accent">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-6 py-5 text-normal text-accent lg:flex-row lg:items-start lg:justify-between">
                <p class="shrink-0">
                    © {{ new Date().getFullYear() }} {{ branch.name }}
                </p>

                <div class="flex flex-col gap-2 text-sm lg:flex-row lg:flex-wrap lg:justify-end lg:gap-x-6 lg:gap-y-2 lg:text-normal">
                    <p v-if="companyName">
                        Prevádzkovateľ: {{ companyName }}
                    </p>

                    <p v-if="companyIco">
                        IČO: {{ companyIco }}
                    </p>

                    <p v-if="companyDic">
                        DIČ: {{ companyDic }}
                    </p>

                    <p v-if="companyIcDph">
                        IČ DPH: {{ companyIcDph }}
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>