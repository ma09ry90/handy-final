<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import axios from 'axios';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone_number: '',
    birthdate: '',
    password: '',
    password_confirmation: ''
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const submit = async () => {
    processing.value = true;
    errors.value = {};
    
    try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/api/register/buyer', form);
        window.location.href = '/login'; 
    } catch (error: any) {
        if (error.response?.status === 422) {
            const data = error.response.data.errors;
            for (const key in data) {
                errors.value[key] = data[key][0];
            }
        } else {
            errors.value.email = 'Registration failed. Please check your inputs.';
        }
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <AuthBase
        title="Create Buyer Account"
        description="Start shopping unique items today"
    >
        <Head title="Register Buyer" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <!-- Name Fields -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="first_name">First Name</Label>
                        <Input id="first_name" type="text" v-model="form.first_name" required placeholder="John" />
                        <InputError :message="errors.first_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="last_name">Last Name</Label>
                        <Input id="last_name" type="text" v-model="form.last_name" required placeholder="Doe" />
                        <InputError :message="errors.last_name" />
                    </div>
                </div>

                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" v-model="form.email" required placeholder="email@example.com" />
                    <InputError :message="errors.email" />
                </div>

                <!-- Phone -->
                <div class="grid gap-2">
                    <Label for="phone">Phone Number</Label>
                    <Input id="phone" type="text" v-model="form.phone_number" required placeholder="0912345678" />
                    <p class="text-xs text-muted-foreground">Must start with 09 or 07, 10 digits.</p>
                    <InputError :message="errors.phone_number" />
                </div>

                <!-- Birthdate -->
                <div class="grid gap-2">
                    <Label for="birthdate">Date of Birth</Label>
                    <Input id="birthdate" type="date" v-model="form.birthdate" required />
                    <p class="text-xs text-muted-foreground">You must be 18+.</p>
                    <InputError :message="errors.birthdate" />
                </div>

                <!-- Password -->
                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input id="password" type="password" v-model="form.password" required />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input id="password_confirmation" type="password" v-model="form.password_confirmation" required />
                </div>

                <Button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700" :disabled="processing">
                    <Spinner v-if="processing" />
                    Register
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <Link href="/login" class="text-emerald-600 hover:underline">Sign in</Link>
            </div>
        </form>
    </AuthBase>
</template>