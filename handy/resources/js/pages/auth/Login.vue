<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';

// Inertia's useForm handles CSRF and error objects automatically
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    // Use POST to the standard web route, not the API route
    form.post('/login', {
        onFinish: () => {
            // Clear the password after the request finishes
            form.reset('password');
        },
    });
};
</script>

<template>
    <AuthBase
        title="Sign in to your account"
        description="Enter your credentials below"
    >
        <Head title="Log in" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        autofocus
                        placeholder="email@example.com"
                    />
                    <!-- This will ONLY show validation errors (like "Invalid credentials"), not 500 errors -->
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password -->
                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    <Spinner v-if="form.processing" class="mr-2" />
                    <span v-if="!form.processing">Log in</span>
                </Button>
            </div>
            
            <!-- Add Forgot Password / Register links if needed -->
        </form>
    </AuthBase>
</template>