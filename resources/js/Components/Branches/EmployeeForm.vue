<script setup>
import Button from 'primevue/button';
import FileUpload from 'primevue/fileupload';
import InputMask from 'primevue/inputmask';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';

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

const phoneCountries = [
    {
        label: 'Slovakia',
        value: 'SK',
        dialCode: '+421',
        flag: '🇸🇰',
        mask: '999 999 999',
        placeholder: '900 123 456',
    },
    {
        label: 'Czech Republic',
        value: 'CZ',
        dialCode: '+420',
        flag: '🇨🇿',
        mask: '999 999 999',
        placeholder: '777 123 456',
    },
    {
        label: 'Austria',
        value: 'AT',
        dialCode: '+43',
        flag: '🇦🇹',
        mask: '999 999 9999',
        placeholder: '660 123 4567',
    },
    {
        label: 'Hungary',
        value: 'HU',
        dialCode: '+36',
        flag: '🇭🇺',
        mask: '99 999 9999',
        placeholder: '30 123 4567',
    },
    {
        label: 'Poland',
        value: 'PL',
        dialCode: '+48',
        flag: '🇵🇱',
        mask: '999 999 999',
        placeholder: '500 123 456',
    },
];

const selectedPhoneCountryCode = ref('SK');
const localPhoneNumber = ref('');

const selectedPhoneCountry = computed(() => {
    return phoneCountries.find((country) => country.value === selectedPhoneCountryCode.value) ?? phoneCountries[0];
});

const stripDialCode = (phone) => {
    let value = String(phone || '').trim();

    const matchedCountry = phoneCountries.find((country) => value.startsWith(country.dialCode));

    if (matchedCountry) {
        selectedPhoneCountryCode.value = matchedCountry.value;
        value = value.replace(matchedCountry.dialCode, '').trim();
    }

    return value;
};

const syncLocalPhoneFromForm = () => {
    localPhoneNumber.value = stripDialCode(props.form.phone);
};

const syncFormPhoneFromLocal = () => {
    const cleanLocalPhone = String(localPhoneNumber.value || '').trim();

    props.form.phone = cleanLocalPhone
        ? `${selectedPhoneCountry.value.dialCode} ${cleanLocalPhone}`
        : '';
};

watch(
    () => props.form.phone,
    () => {
        const currentFullPhone = localPhoneNumber.value
            ? `${selectedPhoneCountry.value.dialCode} ${localPhoneNumber.value}`.trim()
            : '';

        if (props.form.phone !== currentFullPhone) {
            syncLocalPhoneFromForm();
        }
    },
    {
        immediate: true,
    },
);

watch(localPhoneNumber, () => {
    syncFormPhoneFromLocal();
});

watch(selectedPhoneCountryCode, () => {
    syncFormPhoneFromLocal();
});

const handleEmployeePhoto = (event) => {
    props.form.photo = event.files?.[0] ?? null;
};
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ heading || submitLabel }}
            </h2>

            <p v-if="description" class="mt-1 text-sm leading-6 text-slate-600">
                {{ description }}
            </p>
        </div>

        <div class="space-y-6">
            <div>
                <h3 class="text-base font-semibold text-slate-900">
                    Osobné údaje
                </h3>

                <p class="mt-1 text-sm text-slate-600">
                    Tituly, meno a pracovná pozícia zamestnanca.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Titul pred menom
                    </label>

                    <InputText
                        v-model="form.title_before"
                        class="w-full"
                        placeholder="Mgr., PhDr., MUDr."
                    />

                    <p v-if="form.errors.title_before" class="mt-1 text-sm text-red-600">
                        {{ form.errors.title_before }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Titul za menom
                    </label>

                    <InputText
                        v-model="form.title_after"
                        class="w-full"
                        placeholder="PhD., MBA"
                    />

                    <p v-if="form.errors.title_after" class="mt-1 text-sm text-red-600">
                        {{ form.errors.title_after }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Meno <span class="text-red-500">*</span>
                    </label>

                    <InputText
                        v-model="form.first_name"
                        class="w-full"
                        placeholder="Ján"
                    />

                    <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.first_name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Priezvisko <span class="text-red-500">*</span>
                    </label>

                    <InputText
                        v-model="form.last_name"
                        class="w-full"
                        placeholder="Novák"
                    />

                    <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.last_name }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Pozícia <span class="text-red-500">*</span>
                    </label>

                    <InputText
                        v-model="form.position"
                        class="w-full"
                        placeholder="Klinický psychológ"
                    />

                    <p v-if="form.errors.position" class="mt-1 text-sm text-red-600">
                        {{ form.errors.position }}
                    </p>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-6">
                <h3 class="text-base font-semibold text-slate-900">
                    Kontakt a profil
                </h3>

                <p class="mt-1 text-sm text-slate-600">
                    Nepovinné údaje, ktoré môžu byť použité v profile zamestnanca.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Email
                    </label>

                    <InputText
                        v-model="form.email"
                        class="w-full"
                        placeholder="meno@firma.sk"
                    />

                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Telefón
                    </label>

                    <div class="grid gap-3 sm:grid-cols-[12rem_1fr]">
                        <Select
                            v-model="selectedPhoneCountryCode"
                            :options="phoneCountries"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        >
                            <template #value>
                                <div class="flex items-center gap-2">
                                    <span>{{ selectedPhoneCountry.flag }}</span>
                                    <span class="font-medium">
                                        {{ selectedPhoneCountry.dialCode }}
                                    </span>
                                </div>
                            </template>

                            <template #option="{ option }">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span>{{ option.flag }}</span>
                                        <span>{{ option.label }}</span>
                                    </div>

                                    <span class="text-sm text-slate-500">
                                        {{ option.dialCode }}
                                    </span>
                                </div>
                            </template>
                        </Select>

                        <InputMask
                            v-model="localPhoneNumber"
                            :mask="selectedPhoneCountry.mask"
                            :placeholder="selectedPhoneCountry.placeholder"
                            class="w-full"
                            inputmode="numeric"
                            slotChar=""
                        />
                    </div>

                    <p class="mt-1 text-xs text-slate-500">
                        Vyberte predvoľbu krajiny a zadajte iba čísla. Uloží sa napríklad
                        {{ selectedPhoneCountry.dialCode }} {{ selectedPhoneCountry.placeholder }}.
                    </p>

                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                        {{ form.errors.phone }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Bio
                    </label>

                    <Textarea
                        v-model="form.bio"
                        class="w-full"
                        rows="4"
                        placeholder="Krátky popis, špecializácia alebo prax..."
                    />

                    <p v-if="form.errors.bio" class="mt-1 text-sm text-red-600">
                        {{ form.errors.bio }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Fotografia
                    </label>

                    <div
                        v-if="photoPreviewUrl && !form.photo"
                        class="mb-3 flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <img
                            :src="photoPreviewUrl"
                            alt="Aktuálna fotografia"
                            class="h-16 w-16 rounded-xl object-cover"
                        />

                        <p class="text-sm text-slate-600">
                            Aktuálna fotografia zamestnanca.
                        </p>
                    </div>

                    <FileUpload
                        mode="basic"
                        name="photo"
                        accept="image/*"
                        chooseLabel="Vybrať fotografiu"
                        customUpload
                        auto
                        @select="handleEmployeePhoto"
                    />

                    <p v-if="form.photo" class="mt-2 text-sm text-slate-500">
                        Vybraný súbor: {{ form.photo.name }}
                    </p>

                    <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">
                        {{ form.errors.photo }}
                    </p>
                </div>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-5">
                <Button
                    type="submit"
                    :label="submitLabel"
                    icon="pi pi-save"
                    :loading="loading"
                />
            </div>
        </div>
    </div>
</template>