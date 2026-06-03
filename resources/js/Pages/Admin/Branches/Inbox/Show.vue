<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';

import Button from 'primevue/button';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    message: {
        type: Object,
        required: true,
    },
});

const typeLabel = (type) => {
    return {
        contact_form: 'Kontaktný formulár',
        chat: 'Chat',
        booking: 'Rezervácia',
    }[type] ?? type;
};

const statusLabel = (message) => {
    return message.read_at ? 'Prečítaná' : 'Nová';
};

const createdLabel = (message) => {
    return new Date(message.created_at).toLocaleString('sk-SK');
};

const goBack = () => {
    router.get(route('admin.branches.inbox.index', props.branch.id));
};

const deleteMessage = () => {
    router.delete(
        route('admin.branches.inbox.destroy', [props.branch.id, props.message.id]),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Správa | ${branch.name}`" />

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <button
                        type="button"
                        class="mb-3 text-sm font-medium text-accent hover:underline"
                        @click="goBack"
                    >
                        ← Späť na inbox
                    </button>

                    <h1 class="text-heading font-semibold text-dark">
                        {{ message.title || 'Správa' }}
                    </h1>

                    <p class="mt-2 text-normal text-accent">
                        {{ typeLabel(message.type) }} · {{ statusLabel(message) }}
                    </p>
                </div>

                <Button
                    label="Zmazať"
                    severity="danger"
                    outlined
                    @click="deleteMessage"
                />
            </div>

            <section class="rounded-md border border-soft bg-white p-6">
                <dl class="grid gap-5 md:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-accent/60">
                            Meno
                        </dt>

                        <dd class="mt-1 text-sm font-semibold text-dark">
                            {{ message.sender_name || '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-accent/60">
                            Email
                        </dt>

                        <dd class="mt-1 text-sm font-semibold text-dark">
                            {{ message.sender_email || '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-accent/60">
                            Telefón
                        </dt>

                        <dd class="mt-1 text-sm font-semibold text-dark">
                            {{ message.sender_phone || '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-accent/60">
                            Dátum
                        </dt>

                        <dd class="mt-1 text-sm font-semibold text-dark">
                            {{ createdLabel(message) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-accent/60">
                            Typ
                        </dt>

                        <dd class="mt-1 text-sm font-semibold text-dark">
                            {{ typeLabel(message.type) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-accent/60">
                            Stav
                        </dt>

                        <dd class="mt-1 text-sm font-semibold text-dark">
                            {{ statusLabel(message) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-md border border-soft bg-white p-6">
                <h2 class="text-normal font-semibold text-dark">
                    Obsah správy
                </h2>

                <p class="mt-5 whitespace-pre-line text-normal leading-7 text-accent">
                    {{ message.body }}
                </p>
            </section>

            <section
                v-if="message.booking"
                class="rounded-md border border-soft bg-white p-6"
            >
                <h2 class="text-normal font-semibold text-dark">
                    Súvisiaca rezervácia
                </h2>

                <p class="mt-3 text-sm text-accent">
                    Táto správa je prepojená s rezerváciou #{{ message.booking.id }}.
                </p>
            </section>
        </div>
    </AdminLayout>
</template>