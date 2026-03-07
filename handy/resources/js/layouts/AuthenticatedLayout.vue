<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

// 2. Updated Logout Function
const logout = () => {
    // Inertia's router.post automatically handles the CSRF token
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Navigation Bar -->
        <nav class="bg-white border-b border-gray-100 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Left Side: Logo -->
                    <div class="flex">
                        <!-- Changed route('home') to '/' -->
                        <Link href="/" class="flex items-center shrink-0">
                            <span class="text-2xl font-bold text-emerald-600">Artisan</span>
                            <span class="text-2xl font-bold text-amber-400">Hub</span>
                        </Link>
                        
                        <!-- Navigation Links -->
                        <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                            <!-- Changed route('dashboard') to '/dashboard' -->
                            <Link href="/dashboard" 
                                  class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-900 border-b-2 border-emerald-500">
                                Dashboard
                            </Link>
                        </div>
                    </div>

                    <!-- Right Side: User Menu -->
                    <div class="hidden sm:flex sm:items-center sm:ms-6">
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-600">{{ user?.name }}</span>
                            <button @click="logout" 
                                    class="text-sm text-red-500 hover:text-red-700 font-medium cursor-pointer">
                                Log out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>