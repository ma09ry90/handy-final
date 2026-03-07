<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
// Removed Ziggy import

defineProps<{
    stats: { pending_artisans: number, total_buyers: number, total_artisans: number },
    pendingArtisans: Array<any>
}>();
</script>

<template>
    <Head title="Admin Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-gray-500 text-sm">Pending Approvals</h3>
                        <p class="text-3xl font-bold text-amber-600">{{ stats.pending_artisans }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-gray-500 text-sm">Total Buyers</h3>
                        <p class="text-3xl font-bold text-blue-600">{{ stats.total_buyers }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-gray-500 text-sm">Total Artisans</h3>
                        <p class="text-3xl font-bold text-green-600">{{ stats.total_artisans }}</p>
                    </div>
                </div>

                <!-- Pending Artisans List -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Pending Artisan Applications</h3>
                    </div>
                    
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="artisan in pendingArtisans" :key="artisan.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ artisan.shop_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ artisan.user?.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ new Date(artisan.submitted_at).toLocaleDateString() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <!-- UPDATED: Direct URL -->
                                    <Link :href="`/admin/artisans/${artisan.id}`" class="text-indigo-600 hover:text-indigo-900">
                                        View Details
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="pendingArtisans.length === 0">
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    No pending applications.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>