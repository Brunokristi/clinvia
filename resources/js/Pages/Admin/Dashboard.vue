<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed } from 'vue';

const page = usePage();

const isSuperAdmin = computed(() => page.props.auth?.user?.global_role === 'super_admin');
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div class="max-w-2xl space-y-3">
                        <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                            Dashboard
                        </p>

                        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
                            Vitaj v Clinvia administrácii.
                        </h1>
                    </div>
                </div>
            </div>

            <div v-if="isSuperAdmin" class="flex flex-wrap gap-3">
                <Link :href="route('companies.onboard')">
                    <Button label="Onboardovať firmu" icon="pi pi-plus" />
                </Link>
            </div>
            
        </div>
    </AdminLayout>
</template>