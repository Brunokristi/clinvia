export const primevuePt = {
    button: {
        root: {
            class: [
                'inline-flex items-center justify-center gap-2',
                'rounded-xl px-5 py-3',
                'font-normal text-base',
                'bg-accent text-white',
                'border border-accent',
                'transition-all duration-200',
                'hover:bg-dark hover:border-dark',
                'focus:outline-none focus:ring-2 focus:ring-soft focus:ring-offset-2',
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
};