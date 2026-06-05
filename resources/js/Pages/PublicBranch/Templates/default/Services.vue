<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    services: {
        type: Array,
        default: () => [],
    },
});

const searchTerm = ref('');
const selectedCategory = ref('all');
const expandedCategories = ref({});

const normalizeText = (value) => {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
};

const categoryOptions = computed(() => {
    const categories = props.services
        .map((service) => service.category?.name ?? null)
        .filter(Boolean);

    return [
        { label: 'Všetky kategórie', value: 'all' },
        ...new Set(categories).values(),
    ].flatMap((item) => {
        if (typeof item === 'string') {
            return [{ label: item, value: item }];
        }

        return [item];
    });
});

const filteredServices = computed(() => {
    const query = normalizeText(searchTerm.value).trim();

    return props.services.filter((service) => {
        const matchesCategory = selectedCategory.value === 'all'
            || (service.category?.name ?? 'Ostatné') === selectedCategory.value;

        const searchableText = normalizeText([
            service.name,
            service.short_description,
            service.description,
            service.category?.name,
        ].filter(Boolean).join(' '));

        const matchesSearch = !query || searchableText.includes(query);

        return matchesCategory && matchesSearch;
    });
});

const groupedServices = computed(() => {
    return filteredServices.value.reduce((groups, service) => {
        const categoryName = service.category?.name ?? 'Ostatné';

        if (!groups[categoryName]) {
            groups[categoryName] = [];
        }

        groups[categoryName].push(service);

        return groups;
    }, {});
});

const isCategoryExpanded = (categoryName) => {
    return expandedCategories.value[categoryName] === true;
};

const visibleCategoryServices = (categoryName, categoryServices) => {
    if (isCategoryExpanded(categoryName)) {
        return categoryServices;
    }

    return categoryServices.slice(0, 4);
};

const toggleCategory = (categoryName) => {
    expandedCategories.value[categoryName] = !expandedCategories.value[categoryName];
};

const durationLabel = (service) => {
    if (!service.duration_minutes) {
        return null;
    }

    const sessions = service.duration_sessions || 1;

    return `${sessions} × ${service.duration_minutes} min`;
};
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`Služby | ${branch.name}`" />

        <section>
            <h1 class="text-heading font-semibold text-dark">
                Ponúkané služby
            </h1>

            <p class="mt-3 max-w-2xl text-normal leading-6 text-accent">
                Prehľad služieb poskytovaných v {{ branch.name }}.
            </p>
        </section>

        <section>
            <div class="my-8 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Hľadať v službách
                    </label>

                    <IconField class="w-full">
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="searchTerm"
                            class="w-full"
                            :placeholder="Hľadať"
                        />
                    </IconField>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Kategória
                    </label>

                    <Select
                        v-model="selectedCategory"
                        :options="categoryOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
            </div>

            <div
                v-if="filteredServices.length"
                class="space-y-8"
            >
                <div
                    v-for="(categoryServices, categoryName) in groupedServices"
                    :key="categoryName"
                    class="space-y-3"
                >
                    <div>
                        <h2 class="text-normal font-semibold text-dark">
                            {{ categoryName }}
                        </h2>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <Link
                            v-for="service in visibleCategoryServices(categoryName, categoryServices)"
                            :key="service.id"
                            :href="route('public.branch.services.show', [branch.slug, service.slug])"
                            class="rounded-md border border-accent bg-accent p-5 text-white transition hover:scale-[1.01] hover:bg-accent/90"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-soft text-accent">
                                    <i
                                        v-if="service.icon"
                                        :class="service.icon"
                                    />

                                    <span
                                        v-else
                                        class="font-semibold"
                                    >
                                        S
                                    </span>
                                </div>

                                <h3 class="text-normal font-semibold text-white">
                                    {{ service.name }}
                                </h3>
                            </div>

                            <div class="mt-3">
                                <p class="text-sm leading-6 text-white/80">
                                    {{ service.short_description }}
                                </p>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span
                                        v-if="durationLabel(service)"
                                        class="rounded-md bg-dark px-3 py-1 text-xs font-medium text-soft"
                                    >
                                        {{ durationLabel(service) }}
                                    </span>

                                    <span
                                        v-if="service.self_pay_amount"
                                        class="rounded-md bg-dark px-3 py-1 text-xs font-medium text-soft"
                                    >
                                        Samoplatca {{ service.self_pay_amount }} €
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div
                        v-if="categoryServices.length > 4"
                        class="flex justify-center"
                    >
                        <button
                            type="button"
                            class="rounded-md border border-accent/20 px-4 py-2 text-sm font-semibold text-accent transition hover:bg-soft"
                            @click="toggleCategory(categoryName)"
                        >
                            {{ isCategoryExpanded(categoryName) ? 'Zobraziť menej' : `Zobraziť viac (${categoryServices.length - 4})` }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="rounded-md bg-soft p-8 text-center"
            >
                <h2 class="text-heading font-semibold text-dark">
                    Nenašli sa žiadne služby
                </h2>

                <p class="mt-2 text-normal text-accent">
                    Skúste upraviť vyhľadávanie alebo zvoliť inú kategóriu.
                </p>
            </div>
        </section>
    </PublicBranchLayout>
</template>