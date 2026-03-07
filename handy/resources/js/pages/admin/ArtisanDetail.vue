<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
// Removed Ziggy import

// 1. Define the structure of your data
interface User {
    id: number;
    name: string;
    email: string;
}

interface Document {
    id: number;
    path: string;
}

interface ArtisanProfile {
    id: number;
    shop_name: string;
    shop_description?: string; 
    business_license_number: string;
    tax_id: string;
    bank_name: string;
    bank_account_number: string;
    user: User;
    identity_document?: Document | null; 
    business_license_document?: Document | null;
    tax_registration_document?: Document | null;
}

// 2. Use the interface in defineProps
const props = defineProps<{
    profile: ArtisanProfile;
}>();

const rejectionReason = ref('');

const approveForm = useForm({});
const rejectForm = useForm({ rejection_reason: '' });

const approve = () => {
    if(confirm('Are you sure you want to approve this artisan?')) {
        // UPDATED: Direct URL
        approveForm.post(`/admin/artisans/${props.profile.id}/approve`);
    }
};

const reject = () => {
    if(!rejectionReason.value) {
        alert('Please provide a reason for rejection.');
        return;
    }
    rejectForm.rejection_reason = rejectionReason.value;
    // UPDATED: Direct URL
    rejectForm.post(`/admin/artisans/${props.profile.id}/reject`);
};
</script>

<template>
    <Head title="Artisan Verification" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Verify: {{ profile.shop_name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Details Card -->
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>Owner:</strong> {{ profile.user?.name }}</div>
                        <div><strong>Email:</strong> {{ profile.user?.email }}</div>
                        <div><strong>License #:</strong> {{ profile.business_license_number }}</div>
                        <div><strong>Tax ID:</strong> {{ profile.tax_id }}</div>
                        <div class="col-span-2"><strong>Bank:</strong> {{ profile.bank_name }} ({{ profile.bank_account_number }})</div>
                    </div>
                </div>

                <!-- Documents Card -->
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Uploaded Documents</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="font-semibold text-sm mb-1">Identity Document</p>
                            <a v-if="profile.identity_document" :href="'/storage/' + profile.identity_document.path" target="_blank" class="text-blue-600 underline text-sm">View File</a>
                            <p v-else class="text-gray-400 text-sm">Not uploaded</p>
                        </div>
                        <div>
                            <p class="font-semibold text-sm mb-1">Business License</p>
                            <a v-if="profile.business_license_document" :href="'/storage/' + profile.business_license_document.path" target="_blank" class="text-blue-600 underline text-sm">View File</a>
                        </div>
                        <div>
                            <p class="font-semibold text-sm mb-1">Tax Document</p>
                            <a v-if="profile.tax_registration_document" :href="'/storage/' + profile.tax_registration_document.path" target="_blank" class="text-blue-600 underline text-sm">View File</a>
                        </div>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                    
                    <!-- Approve Button -->
                    <button @click="approve" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mr-4">
                        Approve Application
                    </button>

                    <!-- Reject Section -->
                    <div class="mt-6 border-t pt-4">
                        <label class="block text-sm font-medium text-gray-700">Rejection Reason (Required if rejecting)</label>
                        <!-- Added border class for visibility -->
                        <textarea v-model="rejectionReason" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm" rows="3" placeholder="e.g., Business license is blurry."></textarea>
                        <button @click="reject" class="mt-2 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                            Reject Application
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>