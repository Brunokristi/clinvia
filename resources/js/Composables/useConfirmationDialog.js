import { reactive } from 'vue';

const baseState = {
    visible: false,
    title: '',
    message: '',
    confirmLabel: 'Potvrdiť',
    cancelLabel: 'Zrušiť',
    confirmSeverity: 'danger',
    icon: 'pi pi-exclamation-triangle',
    onConfirm: null,
};

export const useConfirmationDialog = (initialState = {}) => {
    const dialog = reactive({
        ...baseState,
        ...initialState,
    });

    const openDialog = (options = {}) => {
        Object.assign(dialog, baseState, initialState, options, {
            visible: true,
        });
    };

    const closeDialog = () => {
        dialog.visible = false;
        dialog.onConfirm = null;
    };

    const confirmDialog = () => {
        const onConfirm = dialog.onConfirm;

        closeDialog();

        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    };

    return {
        dialog,
        openDialog,
        closeDialog,
        confirmDialog,
    };
};