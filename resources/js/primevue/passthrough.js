export const primevuePt = {
    button: {
        root: {
            class: [
                '!rounded-md !px-2 !w-full',
                '!text-normal',
                '!bg-accent !text-white',
                '!border !border-accent',
                'transition-all duration-200',
                'hover:!bg-dark !hover:border-dark',
                'focus:!outline-none ',
                'disabled:pointer-events-none disabled:opacity-50',
            ],
        },
        label: {
            class: 'font-medium',
        },
        icon: {
            class: 'text-sm',
        },
    },

    datatable: {
        root: {
            class: [
                'w-full overflow-hidden',
                'rounded-2xl border border-soft',
                'bg-white shadow-sm',
            ],
        },

        header: {
            class: [
                'border-b border-soft',
                'bg-soft/40 px-5 py-4',
                'font-heading text-dark',
            ],
        },

        table: {
            class: 'w-full text-sm',
        },

        headerRow: {
            class: 'border-b border-soft bg-soft/30',
        },

        column: {
            headerCell: {
                class: [
                    'px-5 py-4 text-left',
                    'font-heading text-sm uppercase tracking-wide',
                    'text-dark',
                ],
            },
            bodyCell: {
                class: [
                    'px-5 py-4',
                    'font-normal text-sm text-gray-700',
                ],
            },
        },

        bodyRow: {
            class: [
                'border-b border-soft/60',
                'transition-colors duration-150',
                'hover:bg-soft/30',
            ],
        },

        paginator: {
            root: {
                class: [
                    'border-t border-soft',
                    'bg-white px-5 py-4',
                ],
            },
        },
    },

    inputtext: {
        root: {
            class: [
                '!w-full !rounded-md',
                '!bg-soft px-4 py-2 !border-soft shadow-0',
                '!text-normal !text-accent',
                'hover:!border-accent',
                'focus:!border-accent focus:!bg-white',
            ],
        },
    },
    password: {
        maskicon: {
            class: '!text-accent',
        },
        unmaskicon: {
            class: '!text-accent',
        },
    },
    checkbox: {
        root: {
            class: 'inline-flex items-center',
        },

        input: {
            class: 'peer',
        },

        box: {
            class: [
                'h-5 w-5 rounded-md',
                'border !border-accent',
                'bg-white',
                'transition-all duration-200',

                // checked
                'peer-checked:!border-accent',
                'peer-checked:!bg-accent',

                // hover / focus
                'peer-hover:!border-accent',
                'peer-focus-visible:ring-2',
                'peer-focus-visible:ring-soft',
                'peer-focus-visible:ring-offset-2',
            ],
        },

        icon: {
            class: 'text-white text-xs',
        },
    },
};