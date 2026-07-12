<script setup>
import InputText from 'primevue/inputtext';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    validate: {
        type: Boolean,
        default: true,
    },
    placeholder: {
        type: String,
        default: '900101/1234',
    },
});

const emit = defineEmits([
    'update:modelValue',
    'update:rawValue',
    'update:valid',
]);

const inputValue = ref('');

const normalizeBirthNumber = (value) => {
    return String(value || '')
        .replace(/\D/g, '')
        .slice(0, 10);
};

const formatBirthNumber = (value) => {
    const digits = normalizeBirthNumber(value);

    if (digits.length <= 6) {
        return digits;
    }

    return `${digits.slice(0, 6)}/${digits.slice(6)}`;
};

const rawValue = computed(() => {
    return normalizeBirthNumber(inputValue.value);
});

const isValidDate = (year, month, day) => {
    const date = new Date(year, month - 1, day);

    return (
        date.getFullYear() === year
        && date.getMonth() === month - 1
        && date.getDate() === day
    );
};

const resolveFullYear = (year, digitsLength) => {
    if (digitsLength === 9) {
        return 1900 + year;
    }

    const currentYear = new Date().getFullYear();
    const currentShortYear = currentYear % 100;

    return year <= currentShortYear
        ? 2000 + year
        : 1900 + year;
};

const validateBirthNumber = (value) => {
    const digits = normalizeBirthNumber(value);

    if (![9, 10].includes(digits.length)) {
        return false;
    }

    const shortYear = Number(digits.slice(0, 2));
    let month = Number(digits.slice(2, 4));
    const day = Number(digits.slice(4, 6));

    if (month > 50) {
        month -= 50;
    }

    if (month < 1 || month > 12) {
        return false;
    }

    const fullYear = resolveFullYear(shortYear, digits.length);

    if (!isValidDate(fullYear, month, day)) {
        return false;
    }

    /*
     * Ten-digit Slovak birth numbers are normally divisible by 11.
     * The historical exception for some numbers issued before 1985
     * is intentionally not rejected here.
     */
    if (digits.length === 10) {
        const numericValue = BigInt(digits);

        if (numericValue % 11n !== 0n && fullYear >= 1985) {
            return false;
        }
    }

    return true;
};

const isValid = computed(() => {
    if (!props.validate) {
        return true;
    }

    return validateBirthNumber(rawValue.value);
});

const hasValidationError = computed(() => {
    if (props.invalid) {
        return true;
    }

    if (!props.validate || !rawValue.value) {
        return false;
    }

    if (rawValue.value.length < 9) {
        return false;
    }

    return !isValid.value;
});

const updateValue = (event) => {
    const digits = normalizeBirthNumber(event.target.value);
    const formattedValue = formatBirthNumber(digits);

    event.target.value = formattedValue;
    inputValue.value = formattedValue;

    emit('update:modelValue', formattedValue);
    emit('update:rawValue', digits);
    emit('update:valid', validateBirthNumber(digits));
};

const handleKeydown = (event) => {
    const allowedKeys = [
        'Backspace',
        'Delete',
        'Tab',
        'ArrowLeft',
        'ArrowRight',
        'Home',
        'End',
        'Enter',
    ];

    if (allowedKeys.includes(event.key)) {
        return;
    }

    if (event.ctrlKey || event.metaKey) {
        return;
    }

    if (!/^\d$/.test(event.key)) {
        event.preventDefault();
    }
};

const handlePaste = (event) => {
    event.preventDefault();

    const pastedValue = event.clipboardData?.getData('text') ?? '';
    const digits = normalizeBirthNumber(pastedValue);
    const formattedValue = formatBirthNumber(digits);

    inputValue.value = formattedValue;

    emit('update:modelValue', formattedValue);
    emit('update:rawValue', digits);
    emit('update:valid', validateBirthNumber(digits));
};

watch(
    () => props.modelValue,
    (value) => {
        const formattedValue = formatBirthNumber(value);

        if (inputValue.value !== formattedValue) {
            inputValue.value = formattedValue;
        }

        const digits = normalizeBirthNumber(value);

        emit('update:rawValue', digits);
        emit('update:valid', validateBirthNumber(digits));

        if (value && value !== formattedValue) {
            emit('update:modelValue', formattedValue);
        }
    },
    {
        immediate: true,
    },
);
</script>

<template>
    <InputText
        :id="id"
        :model-value="inputValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :invalid="hasValidationError"
        class="w-full"
        inputmode="numeric"
        autocomplete="off"
        maxlength="11"
        @keydown="handleKeydown"
        @input="updateValue"
        @paste="handlePaste"
    />
</template>