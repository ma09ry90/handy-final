<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea'; // Ensure you have this component
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';

const form = useForm({
    // User Info
    first_name: '',
    last_name: '',
    email: '',
    phone_number: '',
    birthdate: '',
    password: '',
    password_confirmation: '',
    
    // Shop Info
    shop_name: '',
    shop_description: '',
    slang: '',
    shop_logo: null as File|null,
    
    // Business Info
    business_license_number: '',
    tax_id: '',
    
    // Bank Info
    bank_name: '',
    bank_account_name: '',
    bank_account_number: '',
    
    // Documents
    identity_document: null as File|null,
    business_license_document: null as File|null,
    tax_registration_document: null as File|null,
});

const submit = () => {
    // forceFormData is REQUIRED for file uploads
    form.post('/register/artisan', {
        forceFormData: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase
        title="Become an Artisan"
        description="Fill in the details below to apply for a seller account."
    >
        <Head title="Artisan Registration" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            
            <!-- Section 1: Personal Info -->
            <div class="space-y-4 border-b pb-6">
                <h3 class="font-semibold text-gray-900">Personal Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="first_name">First Name</Label>
                        <Input id="first_name" type="text" v-model="form.first_name" required />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div>
                        <Label for="last_name">Last Name</Label>
                        <Input id="last_name" type="text" v-model="form.last_name" required />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>
                <div>
                    <Label for="email">Email</Label>
                    <Input id="email" type="email" v-model="form.email" required />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="phone_number">Phone Number</Label>
                        <Input id="phone_number" type="tel" v-model="form.phone_number" required />
                    </div>
                    <div>
                        <Label for="birthdate">Birthdate</Label>
                        <Input id="birthdate" type="date" v-model="form.birthdate" required />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="password">Password</Label>
                        <Input id="password" type="password" v-model="form.password" required />
                    </div>
                    <div>
                        <Label for="password_confirmation">Confirm Password</Label>
                        <Input id="password_confirmation" type="password" v-model="form.password_confirmation" required />
                    </div>
                </div>
            </div>

            <!-- Section 2: Shop Details -->
            <div class="space-y-4 border-b pb-6">
                <h3 class="font-semibold text-gray-900">Shop Details</h3>
                <div>
                    <Label for="shop_name">Shop Name</Label>
                    <Input id="shop_name" type="text" v-model="form.shop_name" required />
                    <InputError :message="form.errors.shop_name" />
                </div>
                <div>
                    <Label for="shop_description">Shop Description (Optional)</Label>
                    <Textarea id="shop_description" v-model="form.shop_description" />
                </div>
                <div>
                    <Label for="shop_logo">Shop Logo (Optional)</Label>
                    <Input id="shop_logo" type="file" @input="form.shop_logo = $event.target.files[0]" accept="image/*" />
                </div>
            </div>

            <!-- Section 3: Business & Bank Info -->
            <div class="space-y-4 border-b pb-6">
                <h3 class="font-semibold text-gray-900">Business & Bank Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="business_license_number">Business License #</Label>
                        <Input id="business_license_number" type="text" v-model="form.business_license_number" required />
                    </div>
                    <div>
                        <Label for="tax_id">Tax ID</Label>
                        <Input id="tax_id" type="text" v-model="form.tax_id" required />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="bank_name">Bank Name</Label>
                        <Input id="bank_name" type="text" v-model="form.bank_name" required />
                    </div>
                    <div>
                        <Label for="bank_account_name">Account Name</Label>
                        <Input id="bank_account_name" type="text" v-model="form.bank_account_name" required />
                    </div>
                    <div>
                        <Label for="bank_account_number">Account Number</Label>
                        <Input id="bank_account_number" type="text" v-model="form.bank_account_number" required />
                    </div>
                </div>
            </div>

            <!-- Section 4: Documents -->
            <div class="space-y-4">
                <h3 class="font-semibold text-gray-900">Verification Documents</h3>
                <p class="text-sm text-gray-500">Upload clear copies of your documents (PDF, JPG, PNG).</p>
                
                <div>
                    <Label for="identity_document">ID Card / Passport</Label>
                    <Input id="identity_document" type="file" @input="form.identity_document = $event.target.files[0]" accept=".pdf,.jpg,.png" required />
                    <InputError :message="form.errors.identity_document" />
                </div>
                
                <div>
                    <Label for="business_license_document">Business License Document</Label>
                    <Input id="business_license_document" type="file" @input="form.business_license_document = $event.target.files[0]" accept=".pdf,.jpg,.png" required />
                    <InputError :message="form.errors.business_license_document" />
                </div>
                
                <div>
                    <Label for="tax_registration_document">Tax Registration Document</Label>
                    <Input id="tax_registration_document" type="file" @input="form.tax_registration_document = $event.target.files[0]" accept=".pdf,.jpg,.png" required />
                    <InputError :message="form.errors.tax_registration_document" />
                </div>
            </div>

            <Button type="submit" class="w-full bg-amber-500 hover:bg-amber-600" :disabled="form.processing">
                <Spinner v-if="form.processing" class="mr-2" />
                Submit Application
            </Button>
        </form>
    </AuthBase>
</template>