import { content } from "@primeuix/themes/aura/confirmdialog";
import { itemIcon } from "@primeuix/themes/aura/tabmenu";
import { tabpanel } from "@primeuix/themes/aura/tabs";

export const primevuePt = {
    button: {
        root: ({ props }) => ({
            class: [
                '!rounded-md !px-4 !py-2',
                '!text-normal !font-medium',
                '!border',
                'focus:!outline-none',
                'disabled:pointer-events-none disabled:opacity-50',

                props.text
                    ? [
                        '!bg-transparent',
                        '!text-accent',
                        '!border-transparent',
                        'hover:!bg-transparent',
                        'hover:!text-dark',
                        'hover:!border-transparent',
                    ]
                    : props.outlined
                        ? [
                            '!bg-transparent',
                            '!text-accent',
                            '!border-accent',
                            'hover:!bg-accent',
                            'hover:!text-white',
                            'hover:!border-accent',
                        ]
                        : [
                            '!bg-accent',
                            '!text-white',
                            '!border-accent',
                            'hover:!bg-dark',
                            'hover:!border-dark',
                        ],
            ],
        }),

        label: {
            class: '!font-medium',
        },

        icon: {
            class: '!text-sm',
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
                '!text-normal !font-medium',
                '!text-white',

            ],
        },

        itemIcon: {
            class: [
                '!text-white',
            ],
        },

        itemLabel: {
            class: [
                '!text-normal',
                '!text-white',
                'hover:!text-dark',
            ],
        },

        separator: {
            class: [
                '!mx-1',
                '!text-white',
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
                'w-full overflow-hidden !rounded-md',
                'bg-white',
            ],
        },

        header: {
            class: [
                '!border-0',
                '!border-b !border-soft',
                '!bg-white',
                '!px-0 !py-4',
                '!text-dark',
            ],
        },

        tableContainer: {
            class: [
                '!bg-white',
            ],
        },

        table: {
            class: [
                'w-full',
                '!text-normal',
                '!bg-white',
            ],
        },

        thead: {
            class: [
                '!bg-white',
            ],
        },

        headerRow: {
            class: [
                '!border-b !border-soft !rounded-md',
                '!bg-white',
            ],
        },

        column: {
            headerCell: {
                class: [
                    '!border-0',
                    '!border-b !border-soft',
                    '!bg-accent',
                    '!px-5 !py-3',
                    '!text-left',
                    '!text-white !text-normal',
                ],
            },

            columnHeaderContent: {
                class: [
                    'flex items-center gap-2',
                    '!text-white !text-normal',
                ],
            },

            headerTitle: {
                class: [
                    '!text-white !text-normal',
                ],
            },

            sortIcon: {
                class: [
                    '!text-white',
                ],
            },

            bodyCell: {
                class: [
                    '!border-0',
                    '!border-b !border-accent',
                    '!px-5 !py-4',
                    '!font-normal !text-normal',
                    '!text-accent',
                    '!bg-transparent',
                ],
            },
        },

        tbody: {
            class: [
                '!bg-white',
            ],
        },

        bodyRow: {
            class: [
                '!bg-white',
                'transition-colors duration-150',
                'hover:!bg-soft/30',
            ],
        },

        emptyMessageCell: {
            class: [
                '!px-5 !py-8',
                '!text-center',
                '!text-accent/70',
                '!bg-white',
            ],
        },
    },

    paginator: {
        root: {
            class: [
                '!border-0',
                '!bg-accent',
                '!px-5 !py-3',
                '!text-accent',
                '!rounded-none',
                '!overflow-x-auto',

                // Force paginator dropdown/select to stay inline.
                '[&_.p-select]:!w-auto',
                '[&_.p-select]:!min-w-20',
                '[&_.p-select]:!max-w-24',
                '[&_.p-select]:!shrink-0',
                '[&_.p-paginator-rpp-dropdown]:!w-auto',
                '[&_.p-paginator-rpp-dropdown]:!min-w-20',
                '[&_.p-paginator-rpp-dropdown]:!max-w-24',
                '[&_.p-paginator-rpp-dropdown]:!shrink-0',
            ],
        },

        content: {
            class: [
                'flex w-full flex-nowrap items-center justify-center gap-3',
                '!bg-transparent',

                // Every direct paginator item stays in the same row.
                '[&>*]:shrink-0',

                // Page buttons stay inline.
                '[&_.p-paginator-pages]:flex',
                '[&_.p-paginator-pages]:flex-nowrap',
                '[&_.p-paginator-pages]:items-center',
                '[&_.p-paginator-pages]:gap-2',

                // Report text does not wrap.
                '[&_.p-paginator-current]:whitespace-nowrap',
                '[&_.p-paginator-current]:shrink-0',

                // Rows-per-page select does not stretch.
                '[&_.p-select]:!w-auto',
                '[&_.p-select]:!min-w-20',
                '[&_.p-select]:!max-w-24',
                '[&_.p-select]:!shrink-0',
                '[&_.p-paginator-rpp-dropdown]:!w-auto',
                '[&_.p-paginator-rpp-dropdown]:!min-w-20',
                '[&_.p-paginator-rpp-dropdown]:!max-w-24',
                '[&_.p-paginator-rpp-dropdown]:!shrink-0',
            ],
        },

        first: {
            class: [
                'shrink-0',
                '!h-9 !min-w-9',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                '!shadow-none',
                'transition-all duration-150',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',
                'disabled:!opacity-40',
                'disabled:hover:!border-soft',
                'disabled:hover:!bg-soft',
                'disabled:hover:!text-accent',
            ],
        },

        prev: {
            class: [
                'shrink-0',
                '!h-9 !min-w-9',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                '!shadow-none',
                'transition-all duration-150',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',
                'disabled:!opacity-40',
                'disabled:hover:!border-soft',
                'disabled:hover:!bg-soft',
                'disabled:hover:!text-accent',
            ],
        },

        next: {
            class: [
                'shrink-0',
                '!h-9 !min-w-9',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                '!shadow-none',
                'transition-all duration-150',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',
                'disabled:!opacity-40',
                'disabled:hover:!border-soft',
                'disabled:hover:!bg-soft',
                'disabled:hover:!text-accent',
            ],
        },

        last: {
            class: [
                'shrink-0',
                '!h-9 !min-w-9',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                '!shadow-none',
                'transition-all duration-150',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',
                'disabled:!opacity-40',
                'disabled:hover:!border-soft',
                'disabled:hover:!bg-soft',
                'disabled:hover:!text-accent',
            ],
        },

        page: ({ context }) => ({
            class: [
                'shrink-0',
                '!h-9 !min-w-9',
                '!rounded-md',
                '!border',
                '!shadow-none',
                '!text-normal !font-medium',
                'transition-all duration-150',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',

                context.active
                    ? [
                        '!border-dark',
                        '!bg-dark',
                        '!text-soft',
                    ]
                    : [
                        '!border-soft',
                        '!bg-soft',
                        '!text-accent',
                        'hover:!border-accent',
                        'hover:!bg-accent',
                        'hover:!text-white',
                    ],
            ],
        }),

        pages: {
            class: [
                'flex shrink-0 flex-nowrap items-center justify-center gap-2',
            ],
        },

        current: {
            class: [
                'shrink-0',
                'whitespace-nowrap',
                '!mx-2',
                '!text-normal',
                '!font-semibold',
                '!text-soft',
            ],
        },

        rowPerPageDropdown: {
            root: {
                class: [
                    'shrink-0',
                    '!ml-2',
                    '!w-auto',
                    '!min-w-20',
                    '!rounded-md',
                    '!border !border-soft',
                    '!bg-soft',
                    '!shadow-none',
                    'hover:!border-accent',
                    'focus-within:!border-accent',
                ],
            },

            label: {
                class: [
                    'whitespace-nowrap',
                    '!px-3 !py-2',
                    '!text-normal',
                    '!text-accent',
                ],
            },

            dropdown: {
                class: [
                    'shrink-0',
                    '!w-8',
                    '!text-accent',
                ],
            },
        },
    },

    inputtext: {
        root: {
            class: [
                '!w-full !rounded-md',
                '!bg-soft px-4 py-2 !border-soft !shadow-none',
                '!text-normal !text-accent',
                'hover:!border-accent',
                'focus:!border-accent focus:!bg-white',
                'focus:!ring-0',
            ],
        },
    },

    inputIcon: {
        root: {
            class: [
                '!text-accent',
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

    stepper: {
        root: {
            class: [
                'w-full',
            ],
        },
    },

    steplist: {
        root: {
            class: [
                '!bg-white',
            ],
        },
    },

    step: {
        root: ({ context }) => ({
            class: [
                'group',
                'flex flex-1 items-center gap-3 !p-0',
                'transition-all duration-200',
                context.active ? 'is-active-step' : '',
                context.highlighted ? 'is-highlighted-step' : '',
                context.disabled ? 'pointer-events-none opacity-50' : '',
            ],
        }),

        header: ({ context }) => ({
            class: [
                'flex w-full items-center gap-3',
                '!rounded-md',
                '!px-4 !py-3',
                'transition-all duration-200',
                'hover:!bg-soft/60',
                context.active ? '!bg-accent !text-white' : '!bg-soft/40 !text-accent',
            ],
        }),

        number: ({ context }) => ({
            class: [
                'flex h-8 w-8 shrink-0 items-center justify-center !border-0 !shadow-none',
                '!rounded-full',
                '!text-sm !font-semibold',
                'transition-all duration-200',
                context.active
                    ? '!bg-white !text-accent'
                    : '!bg-accent !text-white',
            ],
        }),

        title: ({ context }) => ({
            class: [
                '!font-heading !text-sm !font-semibold',
                context.active ? '!text-white' : '!text-dark',
            ],
        }),

        separator: {
            class: [
                '!mx-3',
                '!h-px',
                '!bg-soft',
            ],
        },
    },

    steppanels: {
        root: {
            class: [
                '!bg-transparent',
                '!p-0',
            ],
        },
    },

    steppanel: {
        root: {
            class: [
                '!bg-transparent',
                '!p-0',
            ],
        },

        content: {
            class: [
                '!bg-transparent',
                '!p-0',
            ],
        },
    },

    select: {
        root: {
            class: [
                '!w-full',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!shadow-none',
                'transition-all duration-200',
                'hover:!border-accent',
                'focus-within:!border-accent',
                'focus-within:!bg-white',
                'focus-within:!ring-0',
            ],
        },

        label: {
            class: [
                '!px-4 !py-2',
                '!text-normal',
                '!text-accent',
                '!shadow-none',
                'placeholder:!text-accent/50',
            ],
        },

        dropdown: {
            class: [
                '!w-10',
                '!text-accent',
                '!bg-transparent',
                '!rounded-r-md',
            ],
        },

        dropdownIcon: {
            class: [
                '!text-sm',
                '!text-accent',
            ],
        },

        overlay: {
            class: [
                '!rounded-md',
                '!border !border-soft',
                '!bg-white',
                '!shadow-lg',
                '!overflow-hidden',
            ],
        },

        listContainer: {
            class: [
                '!bg-white',
            ],
        },

        list: {
            class: [
                '!p-1',
                '!space-y-1',
                '!bg-white',
            ],
        },

        option: {
            class: [
                '!rounded-md',
                '!px-3 !py-2',
                '!text-normal',
                '!text-accent',
                'transition-all duration-150',
                'hover:!bg-soft',
                'data-[p-selected=true]:!bg-accent',
                'data-[p-selected=true]:!text-white',
                'data-[p-focused=true]:!bg-soft',
                'data-[p-focused=true]:!text-accent',
            ],
        },

        optionLabel: {
            class: [
                '!font-medium',
            ],
        },

        emptyMessage: {
            class: [
                '!px-3 !py-2',
                '!text-sm',
                '!text-accent/70',
            ],
        },
    },

    autocomplete: {
        root: {
            class: [
                '!w-full',
            ],
        },

        inputMultiple: {
            class: [
                '!w-full',
                '!min-h-[42px]',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!px-3 !py-2',
                '!shadow-none',
                'flex flex-wrap items-center gap-2',
                'transition-all duration-200',
                'hover:!border-accent',
                'focus-within:!border-accent',
                'focus-within:!bg-white',
                'focus-within:!ring-0',
            ],
        },

        chipItem: {
            class: [
                '!rounded-md',
                '!bg-accent',
                '!px-2.5 !py-1',
                '!text-normal',
                '!font-medium',
                '!text-white',
                'inline-flex items-center gap-2',
            ],
        },

        chipIcon: {
            class: [
                '!text-white/80',
                '!text-[11px]',
                'cursor-pointer',
                'transition-colors duration-150',
                'hover:!text-white',
            ],
        },

        inputChip: {
            class: [
                '!border-0',
                '!bg-transparent',
                '!p-0',
                '!shadow-none',
                '!outline-none',
                '!text-normal',
                '!text-accent',
                'placeholder:!text-accent/50',
                'focus:!ring-0',
            ],
        },

        pcInputText: {
            root: {
                class: [
                    '!w-full',
                    '!rounded-md',
                    '!border !border-soft',
                    '!bg-soft',
                    '!px-4 !py-2',
                    '!shadow-none',
                    '!text-normal',
                    '!text-accent',
                    'placeholder:!text-accent/50',
                    'transition-all duration-200',
                    'hover:!border-accent',
                    'focus:!border-accent',
                    'focus:!bg-white',
                    'focus:!ring-0',
                ],
            },
        },

        dropdown: {
            class: [
                '!w-10',
                '!rounded-r-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                'transition-all duration-200',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',
            ],
        },

        dropdownIcon: {
            class: [
                '!text-sm',
                '!text-current',
            ],
        },

        overlay: {
            class: [
                '!mt-1',
                '!overflow-hidden',
                '!rounded-md',
                '!border !border-soft',
                '!bg-white',
                '!shadow-lg',
            ],
        },

        listContainer: {
            class: [
                '!bg-white',
            ],
        },

        list: {
            class: [
                '!space-y-1',
                '!bg-white',
                '!p-1',
            ],
        },

        optionGroup: {
            class: [
                '!rounded-md',
                '!bg-dark',
                '!px-3 !py-2',
                '!text-normal',
                '!font-semibold',
                '!text-white',
            ],
        },

        option: {
            class: [
                '!rounded-md',
                '!px-3 !py-2',
                '!text-normal',
                '!text-accent',
                'transition-all duration-150',
                'hover:!bg-soft',
                'hover:!text-dark',
                'data-[p-focused=true]:!bg-soft',
                'data-[p-focused=true]:!text-dark',
                'data-[p-selected=true]:!bg-accent',
                'data-[p-selected=true]:!text-white',
            ],
        },

        optionLabel: {
            class: [
                '!font-medium',
            ],
        },

        emptyMessage: {
            class: [
                '!px-3 !py-2',
                '!text-sm',
                '!text-accent/70',
            ],
        },
    },

    dialog: {
        root: {
            class: [
                '!w-[500px] !max-w-lg',
            ],
            mask: {
                class: [
                    '!bg-accent/50',
                ],
            },
        },

        pcCloseButton: {
            root: {
                class: [
                    '!w-9 !h-9',
                    '!bg-transparent !border-0',
                    'focus:!outline-none',
                    'hove:!bg-transparent',
                ],
            },
            icon: {
                class: [
                    '!text-accent',
                ],
            },
        },
    },

    toast: {
        messageContent: {
            class: [
                '!bg-white',
                '!shadow-lg',
                '!rounded-md',
                '!px-4 !py-3',
                '!text-normal !text-accent',
            ],
        },

        summary: {
            class: [
                '!text-normal',
                '!text-dark !font-semibold',
            ],
        },

        detail: {
            class: [
                '!text-normal',
                '!text-accent',
            ],
        },

        closeButton: {
            class: [
                '!text-accent',
                'hover:!text-dark',
            ],
        },
    },

    textarea: {
        root: {
            class: [
                '!w-full !rounded-md',
                '!bg-soft px-4 py-2 !border-soft shadow-none',
                '!text-normal !text-accent',
                'hover:!border-accent',
                'focus:!border-accent focus:!bg-white',
                '[&::placeholder]:!text-accent/50',
            ],
        },
    },

    avatar: {
        root: {
            class: [
                '!rounded-full',
                '!bg-accent !text-white !text-normal !font-semibold',
            ],
        },
    },

    datepicker: {
        root: {
            class: [
                '!w-full',
            ],
        },

        pcInputText: {
            root: {
                class: [
                    '!w-full !rounded-md',
                    '!bg-soft px-4 py-2 !border-soft !shadow-none',
                    '!text-normal !text-accent',
                    'placeholder:!text-accent/50',
                    'hover:!border-accent',
                    'focus:!border-accent focus:!bg-white',
                ],
            },
        },

        dropdown: {
            class: [
                '!rounded-r-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
            ],
        },

        inputIcon: {
            class: [
                '!text-accent',
            ],
        },

        panel: {
            class: [
                '!mt-1',
                '!rounded-md',
                '!border !border-soft',
                '!bg-white',
                '!shadow-lg',
                '!p-3',
                '!text-accent',
            ],
        },

        calendarContainer: {
            class: [
                '!bg-white',
            ],
        },

        header: {
            class: [
                '!border-0',
                '!border-b !border-soft',
                '!bg-white',
                '!px-0 !pb-3 !pt-0',
                '!text-accent',
            ],
        },

        title: {
            class: [
                'flex items-center gap-2',
                '!text-normal !font-semibold',
                '!text-dark',
            ],
        },

        selectMonth: {
            class: [
                '!rounded-md',
                '!px-3 !py-2',
                '!text-normal !font-semibold',
                '!text-dark',
                'hover:!bg-soft',
                'hover:!text-accent',
            ],
        },

        selectYear: {
            class: [
                '!rounded-md',
                '!px-3 !py-2',
                '!text-normal !font-semibold',
                '!text-dark',
                'hover:!bg-soft',
                'hover:!text-accent',
            ],
        },

        previousButton: {
            class: [
                '!h-9 !w-9',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',
            ],
        },

        nextButton: {
            class: [
                '!h-9 !w-9',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!text-accent',
                'hover:!border-accent',
                'hover:!bg-accent',
                'hover:!text-white',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',
            ],
        },

        table: {
            class: [
                '!mt-3',
                '!w-full',
                '!border-collapse',
            ],
        },

        tableHeaderRow: {
            class: [
                '!border-0',
            ],
        },

        tableHeaderCell: {
            class: [
                '!p-2',
                '!text-center',
                '!text-small',
                '!font-semibold',
                '!text-accent',
            ],
        },

        tableBodyRow: {
            class: [
                '!border-0',
            ],
        },

        dayCell: {
            class: [
                '!p-1',
                '!text-center',
            ],
        },

        day: ({ context }) => ({
            class: [
                'inline-flex items-center justify-center',
                '!h-9 !w-9',
                '!rounded-md',
                '!text-normal',
                '!font-medium',
                'transition-all duration-150',
                'focus:!outline-none',
                'focus:!ring-2',
                'focus:!ring-soft',

                context.selected
                    ? [
                        '!bg-accent',
                        '!text-white',
                    ]
                    : [
                        '!bg-transparent',
                        '!text-accent',
                        'hover:!bg-soft',
                        'hover:!text-dark',
                    ],

                context.today && !context.selected
                    ? [
                        '!border !border-accent',
                        '!text-dark',
                    ]
                    : '',

                context.disabled
                    ? [
                        '!opacity-40',
                        '!pointer-events-none',
                    ]
                    : '',
            ],
        }),

        monthView: {
            class: [
                '!mt-3',
                '!grid !grid-cols-3',
                '!gap-2',
            ],
        },

        month: ({ context }) => ({
            class: [
                '!rounded-md',
                '!px-3 !py-2',
                '!text-normal !font-medium',
                'transition-all duration-150',

                context.selected
                    ? [
                        '!bg-accent',
                        '!text-white',
                    ]
                    : [
                        '!bg-soft',
                        '!text-accent',
                        'hover:!bg-accent',
                        'hover:!text-white',
                    ],
            ],
        }),

        yearView: {
            class: [
                '!mt-3',
                '!grid !grid-cols-2',
                '!gap-2',
            ],
        },

        year: ({ context }) => ({
            class: [
                '!rounded-md',
                '!px-3 !py-2',
                '!text-normal !font-medium',
                'transition-all duration-150',

                context.selected
                    ? [
                        '!bg-accent',
                        '!text-white',
                    ]
                    : [
                        '!bg-soft',
                        '!text-accent',
                        'hover:!bg-accent',
                        'hover:!text-white',
                    ],
            ],
        }),

        buttonbar: {
            class: [
                '!mt-3',
                '!border-t !border-soft',
                '!pt-3',
                'flex items-center justify-between gap-2',
            ],
        },

        pcTodayButton: {
            root: {
                class: [
                    '!rounded-md !px-4 !py-2',
                    '!border !border-accent',
                    '!bg-accent',
                    '!text-normal !font-medium',
                    '!text-white',
                    'hover:!bg-dark',
                    'hover:!border-dark',
                ],
            },
        },

        pcClearButton: {
            root: {
                class: [
                    '!rounded-md !px-4 !py-2',
                    '!border !border-accent',
                    '!bg-transparent',
                    '!text-normal !font-medium',
                    '!text-accent',
                    'hover:!bg-accent',
                    'hover:!text-white',
                ],
            },
        },
    },

    menu: {
        root: {
            class: [
                '!border-0 !bg-transparent',
            ],
        },

        content: {
            class: [
                '!rounded-md',
                '!border !border-0',
                '!bg-white',
                '!shadow-lg',
            ],
        },

        item: {
            class: [
                '!rounded-md !px-3 !py-2',
                '!text-normal !text-accent',
                'transition-all duration-150',
                'hover:!bg-soft',
                'data-[p-focused=true]:!bg-soft',
                'data-[p-focused=true]:!text-accent',
            ],
        },

        label: {
            class: [
                '!font-medium',
            ],
        },
    },

    tablist: {
        tabList: {
            class: [
                '!flex !gap-2',
                '!border-0 !bg-transparent !p-0',
                '!mb-2',

                '[&_.p-tab]:!rounded-md',
                '[&_.p-tab]:!border-0',
                '[&_.p-tab]:!bg-soft',
                '[&_.p-tab]:!px-4',
                '[&_.p-tab]:!py-2',
                '[&_.p-tab]:!text-normal',
                '[&_.p-tab]:!font-medium',
                '[&_.p-tab]:!text-accent',
                '[&_.p-tab]:!shadow-none',

                '[&_.p-tab[data-p-active=true]]:!bg-accent',
                '[&_.p-tab[data-p-active=true]]:!text-white',
                '[&_.p-tab[data-p-active=true]]:!border-accent',
            ],
        },
        activeBar: {
            class: [
                '!hidden',
            ],
        },
    },

    tabPanels: {
        root: {
            class: [
                '!p-0',
            ],
        },
    },

    AccordionHeader: {
        root: {
            class: [
                '!rounded-md !px-4 !py-2',
                '!text-normal !font-medium',
                '!text-accent',
                'transition-all duration-200',
                'hover:!bg-soft hover:!text-dark',
                '!bg-soft'
            ],
        },

        toggleIcon: {
            class: [
                '!text-normal',
                '!text-accent',
            ],
        },
    },

    AccordionContent: {
        content: {
            class: [
                '!bg-transparent',
                '!px-4 !py-3',
                '!text-normal',
                '!text-accent',
            ],
        },
    },

    AccordionPanel: {
        root: {
            class: [
                '!border-0 !bg-transparent',
            ],
        },
    },

    ToggleSwitch: {
        slider: {
            class: [
                'before:!bg-white',
                '!bg-accent',
                'peer-checked:!bg-accent',
                'peer-checked:before:translate-x-full',
                'peer-checked:before:!bg-white',
            ],
        },
    },

    multiselect: {
        root: {
            class: [
                '!w-full',
                '!max-w-full',
                '!min-w-0',
                '!rounded-md',
                '!border !border-soft',
                '!bg-soft',
                '!shadow-none',
                'transition-all duration-200',
                'hover:!border-accent',
                'focus-within:!border-accent',
                'focus-within:!bg-white',
                'focus-within:!ring-0',
            ],
        },

        labelContainer: {
            class: [
                '!min-w-0',
                '!max-w-full',
                '!overflow-hidden',
            ],
        },

        label: {
            class: [
                '!px-4 !py-2',
                '!flex !flex-wrap !items-center !gap-1',
                '!min-w-0',
                '!max-w-full',
                '!overflow-hidden',
                '!text-normal',
                '!text-accent',
                '!shadow-none',
                '!whitespace-normal',
                'placeholder:!text-accent/50',
            ],
        },

        dropdown: {
            class: [
                '!w-10',
                'shrink-0',
                '!text-accent',
                '!bg-transparent',
                '!rounded-r-md',
            ],
        },

        dropdownIcon: {
            class: [
                '!text-sm',
                '!text-accent',
            ],
        },

        overlay: {
            class: [
                '!rounded-md',
                '!border !border-soft',
                '!bg-white',
                '!shadow-lg',
                '!overflow-hidden',
                '!max-w-[400px]',
            ],
        },

        header: {
            class: [
                '!px-4 !py-2',
                '!border-b !border-soft',
                '!bg-white',
            ],
        },

        pcFilter: {
            root: {
                class: [
                    '!w-full',
                    '!rounded-md',
                    '!border !border-soft',
                    '!bg-soft',
                    '!px-3 !py-2',
                    '!shadow-none',
                    '!text-normal',
                    '!text-accent',
                    'placeholder:!text-accent/50',
                    'hover:!border-accent',
                    'focus:!border-accent',
                    'focus:!bg-white',
                    'focus:!ring-0',
                ],
            },
        },

        listContainer: {
            class: [
                '!bg-white',
            ],
        },

        list: {
            class: [
                '!p-1',
                '!space-y-1',
                '!bg-white',
            ],
        },

        option: {
            class: [
                '!rounded-md',
                '!px-3 !py-2',
                '!text-normal',
                '!text-accent',
                'transition-all duration-150',
                'hover:!bg-soft',
                'data-[p-selected=true]:!bg-accent',
                'data-[p-selected=true]:!text-white',
                'data-[p-focused=true]:!bg-soft',
                'data-[p-focused=true]:!text-accent',
            ],
        },

        optionLabel: {
            class: [
                '!font-medium',
                '!whitespace-normal',
                '!break-words',
            ],
        },

        checkbox: {
            root: {
                class: [
                    'inline-flex items-center',
                ],
            },

            box: {
                class: [
                    'h-4 w-4 rounded',
                    '!border !border-soft',
                    '!bg-soft',
                    'transition-all duration-200',
                    'data-[p-checked=true]:!border-accent',
                    'data-[p-checked=true]:!bg-accent',
                ],
            },

            icon: {
                class: [
                    '!text-white',
                    '!text-[10px]',
                ],
            },
        },

        chipItem: {
            class: [
                '!min-w-0',
                '!max-w-full',
            ],
        },

        pcChip: {
            root: {
                class: [
                    '!min-w-0',
                    '!rounded-md',
                    '!bg-accent',
                    '!px-2 !py-0.5',
                    '!text-white',
                    '!text-xs !font-medium',
                ],
            },

            label: {
                class: [
                    '!min-w-0',
                    '!max-w-[300px]',
                    '!overflow-hidden',
                    '!truncate',
                    '!text-white',
                ],
            },

            removeIcon: {
                class: [
                    'shrink-0',
                    '!text-white',
                    'hover:!text-white',
                    '!text-[10px]',
                    'cursor-pointer',
                ],
            },
        },

        emptyMessage: {
            class: [
                '!px-3 !py-2',
                '!text-sm',
                '!text-accent/70',
            ],
        },
    },

    chip: {
        root: {
            class: [
                'inline-flex items-center gap-2',
                '!bg-accent',
                '!rounded-md !px-2.5 !py-1',
                '!text-normal !text-soft !font-medium',
            ],
        },

        label: {
            class: [
                'leading-none',
            ],
        },

        icon: {
            class: [
                '!text-soft',
                '!text-xs',
            ],
        },

        removeIcon: {
            class: [
                '!text-soft hover:!text-dark',
                '!text-[11px] cursor-pointer',
                'transition-colors duration-150',
            ],
        },
    },

    tooltip: {
        root: {
            class: [
                '!bg-white',
                '!shadow-lg',
                '!rounded-md',
                '!px-4 !py-3',
                '!text-normal !text-accent',
            ],
        },

        text: {
            class: [
                '!text-normal',
                '!text-dark !font-semibold',
                '!bg-white',
            ],
        },

        arrow: {
            class: [
                '!bg-white',
            ],
        },
    },

};