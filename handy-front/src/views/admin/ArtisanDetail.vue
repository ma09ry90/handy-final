<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios'; 
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const profile = ref(null);
const isLoading = ref(true);
const rejectionReason = ref('');

// Helper function matching the HomeView pattern
// Note: If your backend 'path' already includes 'storage/', remove the 'storage/' + part below.
const getDocumentUrl = (doc) => {
    if (!doc || !doc.path) return null;
    return getImageUrl('storage/' + doc.path);
};

onMounted(async () => {
    // 1. Check if ID exists
    const artisanId = route.params.id;
    console.log("Artisan ID:", artisanId); // Debug: Check console for this

    if (!artisanId) {
        alert("No Artisan ID found.");
        router.push('/admin/dashboard');
        return;
    }

    try {
        // 2. Fetch Data
        const response = await api.get(`/admin/artisans/${artisanId}`);
        profile.value = response.data.profile;
    } catch (error) {
        console.error('Failed to load artisan details', error);
        alert('Could not load data.');
        router.push('/admin/dashboard');
    } finally {
        isLoading.value = false;
    }
});

const approve = async () => {
    if(!confirm('Are you sure you want to approve this artisan?')) return;
    try {
        await api.post(`/admin/approve/artisan/${profile.value.id}`);
        alert('Artisan Approved Successfully!');
        router.push('/admin/dashboard');
    } catch (error) {
        console.error(error);
        alert('Error approving artisan.');
    }
};

const reject = async () => {
    if(!rejectionReason.value) {
        alert('Please provide a reason for rejection.');
        return;
    }
    try {
        await api.post(`/admin/reject/artisan/${profile.value.id}`, {
            rejection_reason: rejectionReason.value
        });
        alert('Artisan Rejected.');
        router.push('/admin/dashboard');
    } catch (error) {
        console.error(error);
        alert('Error rejecting artisan.');
    }
};

const logout = () => {
    authStore.logout();
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        
        <!-- Admin Navigation -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold text-emerald-600">Artisan</span>
                    <span class="text-2xl font-bold text-amber-400">Hub</span>
                    <span class="ml-4 text-xs font-semibold text-gray-400 uppercase tracking-widest">Admin</span>
                </div>
                
                <div class="flex items-center gap-4">
                    <button @click="router.push('/admin/dashboard')" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                        &larr; Back to Dashboard
                    </button>
                    <button @click="logout" class="text-red-500 hover:text-red-700 text-sm font-medium">
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="py-10">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div v-if="isLoading" class="text-center py-20 text-gray-400">Loading details...</div>

                <template v-else-if="profile">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Verify: {{ profile.shop_name }}</h1>
                            <p class="text-gray-500">Submitted on {{ new Date(profile.submitted_at).toLocaleDateString() }}</p>
                        </div>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full bg-amber-100 text-amber-700">Pending</span>
                    </div>
                    <!-- Details Card -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Business Information</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                <div>
                                    <span class="block text-gray-500">Owner Name</span>
                                    <span class="font-semibold text-gray-900">{{ profile.user?.name }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Email Address</span>
                                    <span class="font-semibold text-gray-900">{{ profile.user?.email }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Business License #</span>
                                    <span class="font-semibold text-gray-900">{{ profile.business_license_number }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-500">Tax ID</span>
                                    <span class="font-semibold text-gray-900">{{ profile.tax_id }}</span>
                                </div>
                                <div class="md:col-span-2">
                                    <span class="block text-gray-500">Bank Details</span>
                                    <span class="font-semibold text-gray-900">{{ profile.bank_name }} - Acc: {{ profile.bank_account_number }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Card -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Uploaded Documents</h3>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                            
                            <!-- Identity Doc -->
                            <div class="border rounded-lg p-4 bg-gray-50 text-center">
                                <p class="font-semibold text-sm text-gray-700 mb-2">Identity Document</p>
                                <a 
                                    v-if="profile.identity_document" 
                                    :href="getDocumentUrl(profile.identity_document)" 
                                    target="_blank"
                                    class="inline-block px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md text-sm font-medium hover:bg-emerald-100"
                                >
                                    View File
                                </a>
                                <p v-else class="text-gray-400 text-xs">Not uploaded</p>
                            </div>
                            <!-- Business License Doc -->
                            <div class="border rounded-lg p-4 bg-gray-50 text-center">
                                <p class="font-semibold text-sm text-gray-700 mb-2">Business License</p>
                                <a 
                                    v-if="profile.business_license_document" 
                                    :href="getDocumentUrl(profile.business_license_document)" 
                                    target="_blank"
                                    class="inline-block px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md text-sm font-medium hover:bg-emerald-100"
                                >
                                    View File
                                </a>
                                <p v-else class="text-gray-400 text-xs">Not uploaded</p>
                            </div>

                            <!-- Tax Doc -->
                            <div class="border rounded-lg p-4 bg-gray-50 text-center">
                                <p class="font-semibold text-sm text-gray-700 mb-2">Tax Document</p>
                                <a 
                                    v-if="profile.tax_registration_document" 
                                    :href="getDocumentUrl(profile.tax_registration_document)" 
                                    target="_blank"
                                    class="inline-block px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md text-sm font-medium hover:bg-emerald-100"
                                >
                                    View File
                                </a>
                                <p v-else class="text-gray-400 text-xs">Not uploaded</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Card -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900">Actions</h3>
                        </div>
                        <div class="p-6">
                            <!-- Approve Button -->
                            <button 
                                @click="approve" 
                                class="w-full md:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-md shadow-sm transition"
                            >
                                Approve Application
                            </button>

                            <!-- Reject Section -->
                            <div class="mt-8 border-t pt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Rejection Reason (Required if rejecting)
                                </label>
                                <textarea 
                                    v-model="rejectionReason" 
                                    class="w-full border border-gray-300 rounded-md shadow-sm p-3 focus:ring-red-500 focus:border-red-500" 
                                    rows="3" 
                                    placeholder="e.g., Business license image is not readable."
                                ></textarea>
                                <button 
                                    @click="reject" 
                                    class="mt-4 bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-md transition"
                                >
                                    Reject Application
                                </button>
                            </div>
                        </div>
                    </div>

                </template>

            </div>
        </main>
    </div>
</template>