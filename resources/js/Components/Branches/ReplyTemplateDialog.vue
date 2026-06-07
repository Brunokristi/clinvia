<script setup>
import AppDialog from '@/Components/Dialogs/FormDialog.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';

const visible = defineModel('visible', {
    type: Boolean,
    default: false,
});

const emit = defineEmits([
    'deleted-selected-template',
]);

const props = defineProps({
    label: {
        type: String,
        default: 'Šablóny odpovedí',
    },
    branch: {
        type: Object,
        required: true,
    },
    templates: {
        type: Array,
        default: () => [],
    },
    selectedTemplateId: {
        type: [Number, String, null],
        default: null,
    },
});

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const editingTemplate = ref(null);
const deletingTemplate = ref(null);

const form = useForm({
    name: '',
    subject: '',
    body: '',
});

const isEditing = computed(() => {
    return Boolean(editingTemplate.value);
});

const formTitle = computed(() => {
    return isEditing.value
        ? 'Upraviť šablónu'
        : 'Nová šablóna';
});

const resetForm = () => {
    editingTemplate.value = null;

    form.clearErrors();
    form.reset();

    form.name = '';
    form.subject = '';
    form.body = '';
};

const fillForm = (template) => {
    editingTemplate.value = template;

    form.clearErrors();

    form.name = template.name ?? '';
    form.subject = template.subject ?? '';
    form.body = template.body ?? '';
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('branches.reply-templates.update', [
            props.branch.id,
            editingTemplate.value.id,
        ]), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: resetForm,
        });

        return;
    }

    form.post(route('branches.reply-templates.store', [
        props.branch.id,
    ]), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: resetForm,
    });
};

const askDeleteTemplate = (template) => {
    deletingTemplate.value = template;

    openDialog({
        title: 'Odstrániť šablónu',
        message: `Naozaj chcete odstrániť šablónu „${template.name}“?`,
        confirmLabel: 'Odstrániť',
        confirmSeverity: 'danger',
        onConfirm: deleteTemplate,
    });
};

const deleteTemplate = () => {
    if (!deletingTemplate.value) {
        return;
    }

    const templateId = deletingTemplate.value.id;

    router.delete(route('branches.reply-templates.destroy', [
        props.branch.id,
        templateId,
    ]), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (Number(props.selectedTemplateId) === Number(templateId)) {
                emit('deleted-selected-template');
            }

            if (editingTemplate.value && Number(editingTemplate.value.id) === Number(templateId)) {
                resetForm();
            }

            deletingTemplate.value = null;
            closeDialog();
        },
        onError: () => {
            deletingTemplate.value = null;
            closeDialog();
        },
    });
};

watch(visible, (isVisible) => {
    if (!isVisible) {
        resetForm();
    }
});
</script>

<template>
    <AppDialog
        v-model:visible="visible"
        :title="label"
        width="max-w-4xl"
        close-label="Zavrieť"
        :show-footer="false"
    >
        <div class="grid gap-6">
            <section class="rounded-md border border-soft bg-white p-4">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-normal font-semibold text-dark">
                            {{ formTitle }}
                        </h3>

                        <p class="mt-1 text-normal text-accent">
                            Vytvorte alebo upravte predvolenú odpoveď.
                        </p>
                    </div>

                    <Button
                        v-if="isEditing"
                        type="button"
                        label="Nová"
                        text
                        @click="resetForm"
                    />
                </div>

                <form
                    class="space-y-4"
                    @submit.prevent="submit"
                >
                    <FormField
                        label="Názov šablóny"
                        for="template_name"
                        :required="true"
                        :error="form.errors.name"
                    >
                        <InputText
                            id="template_name"
                            v-model="form.name"
                            class="w-full"
                            :invalid="Boolean(form.errors.name)"
                        />
                    </FormField>

                    <FormField
                        label="Predmet"
                        for="template_subject"
                        :required="true"
                        :error="form.errors.subject"
                    >
                        <InputText
                            id="template_subject"
                            v-model="form.subject"
                            class="w-full"
                            :invalid="Boolean(form.errors.subject)"
                        />
                    </FormField>

                    <FormField
                        label="Text odpovede"
                        for="template_body"
                        :required="true"
                        :error="form.errors.body"
                    >
                        <Textarea
                            id="template_body"
                            v-model="form.body"
                            class="w-full"
                            rows="8"
                            auto-resize
                            :invalid="Boolean(form.errors.body)"
                        />
                    </FormField>

                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            label="Zrušiť"
                            outlined
                            :disabled="form.processing"
                            @click="visible = false"
                        />

                        <Button
                            type="submit"
                            :label="isEditing ? 'Uložiť zmeny' : 'Vytvoriť šablónu'"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />
                    </div>
                </form>
            </section>

            <section class="rounded-md border border-soft bg-soft p-4">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-normal font-semibold text-dark">
                            Existujúce šablóny
                        </h3>

                        <p class="mt-1 text-normal text-accent">
                            Vyberte šablónu, ktorú chcete upraviť alebo odstrániť.
                        </p>
                    </div>
                </div>

                <div
                    v-if="templates.length"
                    class="space-y-3"
                >
                    <article
                        v-for="template in templates"
                        :key="template.id"
                        class="rounded-md bg-white p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-dark">
                                    {{ template.name }}
                                </p>

                                <p class="mt-1 text-xs text-accent">
                                    {{ template.subject || 'Bez predmetu' }}
                                </p>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                <Button
                                    type="button"
                                    label="Upraviť"
                                    rounded
                                    @click="fillForm(template)"
                                />

                                <Button
                                    type="button"
                                    severity="danger"
                                    label="Odstrániť"
                                    outlined
                                    rounded
                                    @click="askDeleteTemplate(template)"
                                />
                            </div>
                        </div>

                        <p class="mt-3 line-clamp-3 whitespace-pre-line text-xs leading-5 text-muted">
                            {{ template.body }}
                        </p>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-md bg-white p-4 text-center"
                >
                    <p class="text-sm font-semibold text-dark">
                        Zatiaľ nemáte žiadne šablóny.
                    </p>

                    <p class="mt-1 text-sm text-accent">
                        Prvú šablónu môžete vytvoriť hore.
                    </p>
                </div>
            </section>
        </div>

        <ConfirmationDialog
            :show="dialog.visible"
            :title="dialog.title"
            :message="dialog.message"
            :confirm-label="dialog.confirmLabel"
            :cancel-label="dialog.cancelLabel"
            :confirm-severity="dialog.confirmSeverity"
            :icon="dialog.icon"
            @cancel="closeDialog"
            @confirm="confirmDialog"
        />
    </AppDialog>
</template>