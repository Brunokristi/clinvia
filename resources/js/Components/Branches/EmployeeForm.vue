<script setup>
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import PhoneInput from '@/Components/Forms/PhoneInput.vue';

import AutoComplete from 'primevue/autocomplete';
import FileUpload from 'primevue/fileupload';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { ref, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    submitLabel: {
        type: String,
        default: 'Uložiť',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    heading: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    photoPreviewUrl: {
        type: String,
        default: '',
    },
});

const selectedPhoneCountryCode = ref('SK');
const localPhoneNumber = ref('');

const positionOptions = [
    {
        label: 'Lekári',
        items: [
            { label: 'Všeobecný lekár pre dospelých', value: 'Všeobecný lekár pre dospelých' },
            { label: 'Všeobecný lekár pre deti a dorast', value: 'Všeobecný lekár pre deti a dorast' },
            { label: 'Pediater', value: 'Pediater' },
            { label: 'Internista', value: 'Internista' },
            { label: 'Kardiológ', value: 'Kardiológ' },
            { label: 'Neurológ', value: 'Neurológ' },
            { label: 'Psychiater', value: 'Psychiater' },
            { label: 'Detský psychiater', value: 'Detský psychiater' },
            { label: 'Dermatovenerológ', value: 'Dermatovenerológ' },
            { label: 'Gynekológ a pôrodník', value: 'Gynekológ a pôrodník' },
            { label: 'Ortopéd', value: 'Ortopéd' },
            { label: 'Chirurg', value: 'Chirurg' },
            { label: 'Traumatológ', value: 'Traumatológ' },
            { label: 'Urológ', value: 'Urológ' },
            { label: 'Oftalmológ', value: 'Oftalmológ' },
            { label: 'Otorinolaryngológ', value: 'Otorinolaryngológ' },
            { label: 'Gastroenterológ', value: 'Gastroenterológ' },
            { label: 'Endokrinológ', value: 'Endokrinológ' },
            { label: 'Diabetológ', value: 'Diabetológ' },
            { label: 'Reumatológ', value: 'Reumatológ' },
            { label: 'Pneumológ a ftizeológ', value: 'Pneumológ a ftizeológ' },
            { label: 'Alergológ a klinický imunológ', value: 'Alergológ a klinický imunológ' },
            { label: 'Hematológ', value: 'Hematológ' },
            { label: 'Onkológ', value: 'Onkológ' },
            { label: 'Rádiológ', value: 'Rádiológ' },
            { label: 'Anestéziológ a intenzivista', value: 'Anestéziológ a intenzivista' },
            { label: 'Lekár urgentnej medicíny', value: 'Lekár urgentnej medicíny' },
            { label: 'Rehabilitačný lekár', value: 'Rehabilitačný lekár' },
            { label: 'Pracovný lekár', value: 'Pracovný lekár' },
            { label: 'Zubný lekár', value: 'Zubný lekár' },
            { label: 'Čeľustný ortopéd', value: 'Čeľustný ortopéd' },
        ],
    },
    {
        label: 'Psychológia a terapia',
        items: [
            { label: 'Klinický psychológ', value: 'Klinický psychológ' },
            { label: 'Poradenský psychológ', value: 'Poradenský psychológ' },
            { label: 'Detský psychológ', value: 'Detský psychológ' },
            { label: 'Psychoterapeut', value: 'Psychoterapeut' },
            { label: 'Školský psychológ', value: 'Školský psychológ' },
            { label: 'Logopéd', value: 'Logopéd' },
            { label: 'Liečebný pedagóg', value: 'Liečebný pedagóg' },
            { label: 'Špeciálny pedagóg', value: 'Špeciálny pedagóg' },
        ],
    },
    {
        label: 'Sestry',
        items: [
            { label: 'Sestra', value: 'Sestra' },
            { label: 'Všeobecná sestra', value: 'Všeobecná sestra' },
            { label: 'Ambulantná sestra', value: 'Ambulantná sestra' },
            { label: 'Praktická sestra', value: 'Praktická sestra' },
            { label: 'Detská sestra', value: 'Detská sestra' },
            { label: 'Psychiatrická sestra', value: 'Psychiatrická sestra' },
            { label: 'Operačná sestra', value: 'Operačná sestra' },
            { label: 'Anesteziologická sestra', value: 'Anesteziologická sestra' },
            { label: 'Sestra intenzívnej starostlivosti', value: 'Sestra intenzívnej starostlivosti' },
            { label: 'Geriatrická sestra', value: 'Geriatrická sestra' },
            { label: 'Komunitná sestra', value: 'Komunitná sestra' },
            { label: 'Pôrodná asistentka', value: 'Pôrodná asistentka' },
            { label: 'Vedúca sestra', value: 'Vedúca sestra' },
            { label: 'Staničná sestra', value: 'Staničná sestra' },
        ],
    },
    {
        label: 'Ostatní zdravotnícki pracovníci',
        items: [
            { label: 'Fyzioterapeut', value: 'Fyzioterapeut' },
            { label: 'Ergoterapeut', value: 'Ergoterapeut' },
            { label: 'Nutričný terapeut', value: 'Nutričný terapeut' },
            { label: 'Zdravotnícky záchranár', value: 'Zdravotnícky záchranár' },
            { label: 'Zdravotnícky laborant', value: 'Zdravotnícky laborant' },
            { label: 'Rádiologický technik', value: 'Rádiologický technik' },
            { label: 'Farmaceut', value: 'Farmaceut' },
            { label: 'Farmaceutický laborant', value: 'Farmaceutický laborant' },
            { label: 'Dentálna hygienička', value: 'Dentálna hygienička' },
            { label: 'Zubný asistent', value: 'Zubný asistent' },
            { label: 'Sanitár', value: 'Sanitár' },
            { label: 'Recepčná', value: 'Recepčná' },
            { label: 'Koordinátor ambulancie', value: 'Koordinátor ambulancie' },
            { label: 'Administratívny pracovník', value: 'Administratívny pracovník' },
        ],
    },
];

const filteredPositionOptions = ref(positionOptions);
const positionQuery = ref('');

const phoneDialCodes = {
    SK: '+421',
    CZ: '+420',
    AT: '+43',
    HU: '+36',
    PL: '+48',
};

const normalizeText = (value) => {
    return String(value || '').toLowerCase().trim();
};

const makePositionOption = (position) => {
    const value = String(position || '').trim();

    if (!value) {
        return null;
    }

    return {
        label: value,
        value,
    };
};

const allPositionItems = () => {
    return positionOptions.flatMap((group) => group.items);
};

const positionExists = (position) => {
    const normalizedPosition = normalizeText(position);

    return allPositionItems().some((item) => {
        return normalizeText(item.value) === normalizedPosition;
    });
};

const selectedPositionExists = (position) => {
    const normalizedPosition = normalizeText(position);

    return props.form.position.some((item) => {
        return normalizeText(item?.value ?? item?.label ?? item) === normalizedPosition;
    });
};

const addCustomPositions = (value) => {
    const positions = String(value || '')
        .split(',')
        .map((position) => position.trim())
        .filter(Boolean);

    if (!positions.length) {
        return;
    }

    const newPositions = positions
        .filter((position) => {
            return !selectedPositionExists(position);
        })
        .map((position) => {
            return makePositionOption(position);
        })
        .filter(Boolean);

    if (!newPositions.length) {
        return;
    }

    props.form.position = [
        ...props.form.position,
        ...newPositions,
    ];

    positionQuery.value = '';
    filteredPositionOptions.value = positionOptions;
};

const searchPositions = (event) => {
    const query = String(event.query || '').trim();

    positionQuery.value = query;

    if (!query) {
        filteredPositionOptions.value = positionOptions;
        return;
    }

    const searchQuery = query
        .split(',')
        .pop()
        ?.trim() ?? '';

    if (!searchQuery) {
        filteredPositionOptions.value = positionOptions;
        return;
    }

    const filteredGroups = positionOptions
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => {
                return normalizeText(item.label).includes(normalizeText(searchQuery));
            }),
        }))
        .filter((group) => {
            return group.items.length > 0;
        });

    const shouldShowCustomOption = !positionExists(searchQuery)
        && !selectedPositionExists(searchQuery);

    filteredPositionOptions.value = shouldShowCustomOption
        ? [
            {
                label: 'Vlastná pozícia',
                items: [
                    {
                        label: `Pridať „${searchQuery}”`,
                        value: searchQuery,
                        is_custom: true,
                    },
                ],
            },
            ...filteredGroups,
        ]
        : filteredGroups;
};

const onPositionSelect = (event) => {
    const selectedPosition = event.value;

    if (!selectedPosition?.is_custom) {
        return;
    }

    props.form.position = props.form.position.map((position) => {
        const value = position?.value ?? position?.label ?? position;

        if (value === selectedPosition.value || position?.is_custom) {
            return makePositionOption(selectedPosition.value);
        }

        return position;
    });
};

const handlePositionKeydown = (event) => {
    if (event.key !== ',') {
        return;
    }

    event.preventDefault();

    const value = event.target?.value || positionQuery.value;

    addCustomPositions(value);

    if (event.target) {
        event.target.value = '';
    }
};

const stripDialCode = (phone) => {
    let value = String(phone || '').trim();

    const matchedCountry = Object.entries(phoneDialCodes).find(([, dialCode]) => {
        return value.startsWith(dialCode);
    });

    if (matchedCountry) {
        const [countryCode, dialCode] = matchedCountry;

        selectedPhoneCountryCode.value = countryCode;
        value = value.replace(dialCode, '').trim();
    }

    return value;
};

watch(
    () => props.form.phone,
    (value) => {
        localPhoneNumber.value = stripDialCode(value);
    },
    {
        immediate: true,
    },
);

const updateFullPhoneValue = (value) => {
    props.form.phone = value;
};

const handleEmployeePhoto = (event) => {
    props.form.photo = event.files?.[0] ?? null;
};
</script>

<template>
    <FormPage
        :submit-label="submitLabel"
        :loading="loading"
    >
        <div
            v-if="heading || description"
            class="space-y-2"
        >
            <h2
                v-if="heading"
                class="text-lg font-semibold text-dark"
            >
                {{ heading }}
            </h2>

            <p
                v-if="description"
                class="text-sm leading-6 text-accent"
            >
                {{ description }}
            </p>
        </div>

        <FormSection
            title="Osobné údaje"
            columns="md:grid-cols-2"
        >
            <FormField
                label="Titul pred menom"
                for="title_before"
                :error="form.errors.title_before"
            >
                <InputText
                    id="title_before"
                    v-model="form.title_before"
                    class="w-full"
                    placeholder="Mgr., PhDr., MUDr."
                    :invalid="Boolean(form.errors.title_before)"
                />
            </FormField>

            <FormField
                label="Titul za menom"
                for="title_after"
                :error="form.errors.title_after"
            >
                <InputText
                    id="title_after"
                    v-model="form.title_after"
                    class="w-full"
                    placeholder="PhD., MBA"
                    :invalid="Boolean(form.errors.title_after)"
                />
            </FormField>

            <FormField
                label="Meno"
                for="first_name"
                required
                :error="form.errors.first_name"
            >
                <InputText
                    id="first_name"
                    v-model="form.first_name"
                    class="w-full"
                    placeholder="Ján"
                    :invalid="Boolean(form.errors.first_name)"
                />
            </FormField>

            <FormField
                label="Priezvisko"
                for="last_name"
                required
                :error="form.errors.last_name"
            >
                <InputText
                    id="last_name"
                    v-model="form.last_name"
                    class="w-full"
                    placeholder="Novák"
                    :invalid="Boolean(form.errors.last_name)"
                />
            </FormField>

            <FormField
                label="Pozícia"
                for="position"
                required
                :error="form.errors.position"
                span="md:col-span-2"
            >
                <AutoComplete
                    id="position"
                    v-model="form.position"
                    :suggestions="filteredPositionOptions"
                    option-label="label"
                    option-group-label="label"
                    option-group-children="items"
                    multiple
                    dropdown
                    dropdown-mode="blank"
                    complete-on-focus
                    placeholder="Vyberte alebo napíšte pozície"
                    class="w-full"
                    input-class="w-full"
                    :invalid="Boolean(form.errors.position)"
                    @complete="searchPositions"
                    @option-select="onPositionSelect"
                    @keydown="handlePositionKeydown"
                >
                    <template #optiongroup="{ option }">
                        <div class="rounded-md bg-dark p-2 text-normal font-semibold text-white">
                            {{ option.label }}
                        </div>
                    </template>

                    <template #option="{ option }">
                        <div class="text-sm text-dark">
                            {{ option.label }}
                        </div>
                    </template>
                </AutoComplete>
            </FormField>
        </FormSection>

        <FormSection
            title="Profil"
            columns="md:grid-cols-2"
        >
            <FormField
                label="Bio"
                for="bio"
                :error="form.errors.bio"
                span="md:col-span-2"
            >
                <Textarea
                    id="bio"
                    v-model="form.bio"
                    class="w-full"
                    rows="4"
                    placeholder="Krátky popis, špecializácia alebo prax..."
                    :invalid="Boolean(form.errors.bio)"
                />
            </FormField>

            <FormField
                label="Fotografia"
                :error="form.errors.photo"
                span="md:col-span-2"
            >
                <div
                    v-if="photoPreviewUrl && !form.photo"
                    class="mb-3 flex items-center gap-4 rounded-md bg-soft p-3"
                >
                    <img
                        :src="photoPreviewUrl"
                        alt="Aktuálna fotografia"
                        class="h-16 w-16 rounded-md object-cover"
                    >

                    <p class="text-sm text-accent">
                        Aktuálna fotografia zamestnanca.
                    </p>
                </div>

                <FileUpload
                    mode="basic"
                    name="photo"
                    accept="image/*"
                    choose-label="Vybrať fotografiu"
                    custom-upload
                    auto
                    @select="handleEmployeePhoto"
                />

                <p
                    v-if="form.photo"
                    class="mt-2 text-sm text-accent"
                >
                    Vybraný súbor: {{ form.photo.name }}
                </p>
            </FormField>
        </FormSection>

        <FormSection
            title="Kontaktné údaje"
            columns="md:grid-cols-2"
        >
            <FormField
                label="Email"
                for="email"
                :error="form.errors.email"
            >
                <InputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="w-full"
                    placeholder="meno@firma.sk"
                    :invalid="Boolean(form.errors.email)"
                />
            </FormField>

            <FormField
                label="Telefón"
                :error="form.errors.phone"
            >
                <PhoneInput
                    v-model="localPhoneNumber"
                    v-model:country-code="selectedPhoneCountryCode"
                    :invalid="Boolean(form.errors.phone)"
                    @update:full-value="updateFullPhoneValue"
                />
            </FormField>
        </FormSection>
    </FormPage>
</template>