<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';

// Placeholder for logged-in user data
defineProps<{
    user?: { name: string, role_id: number }
}>();
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900">
                        Welcome back!
                    </h3>
                    <p class="mt-2 text-gray-600">
                        You are logged in.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>