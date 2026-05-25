<script setup>
import InputMask from 'primevue/inputmask';
import Select from 'primevue/select';
import { computed, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    countryCode: {
        type: String,
        default: 'SK',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:modelValue',
    'update:countryCode',
    'update:fullValue',
]);

const phoneCountries = [
    {
        label: 'Slovensko',
        value: 'SK',
        dialCode: '+421',
        flag: '🇸🇰',
        mask: '999 999 999',
        placeholder: '900 123 456',
    },
    {
        label: 'Česko',
        value: 'CZ',
        dialCode: '+420',
        flag: '🇨🇿',
        mask: '999 999 999',
        placeholder: '777 123 456',
    },
    {
        label: 'Rakúsko',
        value: 'AT',
        dialCode: '+43',
        flag: '🇦🇹',
        mask: '999 999 9999',
        placeholder: '660 123 4567',
    },
    {
        label: 'Maďarsko',
        value: 'HU',
        dialCode: '+36',
        flag: '🇭🇺',
        mask: '99 999 9999',
        placeholder: '30 123 4567',
    },
    {
        label: 'Poľsko',
        value: 'PL',
        dialCode: '+48',
        flag: '🇵🇱',
        mask: '999 999 999',
        placeholder: '500 123 456',
    },
];

const selectedCountry = computed(() => {
    return phoneCountries.find((item) => item.value === props.countryCode) ?? phoneCountries[0];
});

const fullValue = computed(() => {
    const value = String(props.modelValue || '').trim();

    if (!value) {
        return '';
    }

    return `${selectedCountry.value.dialCode} ${value}`.trim();
});

watch(fullValue, (value) => {
    emit('update:fullValue', value);
}, { immediate: true });

const updateCountryCode = (value) => {
    emit('update:countryCode', value);
};

const updateValue = (value) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <div class="grid gap-1 sm:grid-cols-[4rem_1fr]">
        <Select
            :model-value="countryCode"
            :options="phoneCountries"
            option-label="label"
            option-value="value"
            class="w-full"
            :disabled="disabled"
            @update:model-value="updateCountryCode"
        >
            <template #value>
                <div class="flex items-center gap-2">
                    <span>{{ selectedCountry.flag }}</span>
                    <span class="font-medium">{{ selectedCountry.dialCode }}</span>
                </div>
            </template>

            <template #option="{ option }">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span>{{ option.flag }}</span>
                        <span>{{ option.label }}</span>
                    </div>

                    <span class="text-sm text-accent/70">
                        {{ option.dialCode }}
                    </span>
                </div>
            </template>
        </Select>

        <InputMask
            :model-value="modelValue"
            :mask="selectedCountry.mask"
            :placeholder="selectedCountry.placeholder"
            class="w-full"
            inputmode="numeric"
            slot-char=""
            :invalid="invalid"
            :disabled="disabled"
            @update:model-value="updateValue"
        />
    </div>
</template>