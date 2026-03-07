<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    modelValue?: string;
    class?: string;
}>();

const emit = defineEmits(['update:modelValue']);

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});
</script>

<template>
    <textarea
        v-model="value"
        :class="[
            'flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
            props.class
        ]"
    ></textarea>
</template>