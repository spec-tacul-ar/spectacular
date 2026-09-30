<template>
    <DropdownMenu class="items-stretch">
        <template #trigger="{ toggle }">
            <slot name="button" />

            <button
                type="button"
                class="btn btn-primary rounded-l-none border-l border-l-gray-600 px-3 dark:border-l-gray-600"
                @click="toggle">

                <IconSet name="chevron-down" class="size-3" />
            </button>
        </template>

        <slot />
    </DropdownMenu>
</template>

<script>
import DropdownMenu from '@/components/DropdownMenu.vue';
import IconSet from '@/components/IconSet.vue';

export default {
    components: {
        DropdownMenu,
        IconSet,
    },
};
</script>
