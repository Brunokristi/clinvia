import { itemIcon } from "@primeuix/themes/aura/tabmenu";

export const primevuePt = {
    button: {
        root: {
            class: [
                '!rounded-md !px-2 !w-full',
                '!text-normal',
                '!bg-accent !text-white',
                '!border !border-accent',
                'transition-all duration-200',
                'hover:!bg-dark hover:!border-dark',
                'focus:!outline-none',
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

    breadcrumb: {
        root: {
            class: [
                '!border-0 !bg-transparent',
                '!p-0',
            ],
        },

        list: {
            class: [
                'flex flex-wrap items-center gap-2',
                '!text-normal',
            ],
        },

        item: {
            class: [
                'flex items-center',
            ],
        },

        itemLink: {
            class: [
                '!rounded-md !px-2 !py-1',
                '!text-normal !font-medium',
                '!text-accent',
                'transition-all duration-200',
                'hover:!bg-soft hover:!text-accent',
            ],
        },

        itemIcon: {
            class: [
                '!text-accent',
            ],
        },

        itemLabel: {
            class: [
                '!text-normal',
                '!text-accent',
            ],
        },

        separator: {
            class: [
                '!mx-1',
                '!text-accent',
            ],
        },
    },

    panelmenu: {
        root: {
            class: [
                '!border-0 !bg-transparent',
            ],
        },

        panel: {
            class: [
                '!border-0 !bg-transparent',
            ],
        },

        header: {
            class: [
                '!border-0 !bg-transparent',
            ],
        },

        headerContent: {
            class: [
                '!border-0 !bg-transparent',
            ],
        },

        headerLink: {
            class: [
                '!rounded-md',
                '!bg-dark',
                '!text-normal !font-medium !text-white',
                'transition-all duration-200',
                'focus:!outline-none focus:!ring-2 focus:!ring-soft',
            ],
        },

        headerIcon: {
            class: [
                '!text-sm',
                '!text-current',
            ],
        },

        submenuIcon: {
            class: [
                '!text-sm',
                '!text-current',
            ],
        },

        content: {
            class: [
                '!border-0 !bg-transparent',
                '!pt-1 !pb-3',
            ],
        },

        submenu: {
            class: [
                'space-y-1',
            ],
        },

        item: {
            class: [
                '!bg-transparent',
            ],
        },

        itemContent: {
            class: [
                '!bg-transparent',
            ],
        },

        itemLink: {
            class: [
                '!rounded-md !px-4 !py-2',
                '!text-normal !font-normal !text-white',
                'transition-all duration-200',
                'hover:!bg-dark hover:!text-white',
                'focus:!outline-none focus:!ring-2 focus:!ring-soft',
            ],
        },

        itemIcon: {
            class: [
                '!text-sm',
                '!text-current',
            ],
        },

        itemLabel: {
            class: [
                'truncate',
            ],
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
        meterText: {
            class: '!text-normal !text-accent',
        },
        overlay: {
            class: [
                'rounded-md border border-soft',
                'bg-white shadow-lg',
                'p-4',
            ],
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
                'focus:!border focus:!border-accent',
                '!bg-soft !border-soft',
                'transition-all duration-200',

                'peer-checked:!border-accent',
                'peer-checked:!bg-accent',

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