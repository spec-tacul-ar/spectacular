<template>
    <slot name="before" />

    <NavbarButton v-if="project.can_write && project.features.isNotEmpty()" :to="{ name: 'projects.requirements.create' }" icon="plus-lg" tooltip="Add requirement" class="hidden sm:flex" />
    <NavbarButton v-if="project.can_write" :to="{ name: 'projects.organise' }" icon="organise" tooltip="Organise" class="hidden sm:flex" />
    <NavbarButton :to="{ name: 'projects.people' }" icon="people" tooltip="People" class="hidden sm:flex" />
    <NavbarButton :to="{ name: 'projects.feedback' }" icon="feedback" tooltip="Feedback" class="hidden sm:flex" />

    <DropdownMenu class="hidden sm:flex">
        <template #trigger="{ toggle }">
            <Tooltip text="Download">
                <button type="button" class="flex items-center whitespace-nowrap gap-2 p-2 rounded-full border border-gray-100 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800" @click="toggle">
                    <IconSet name="download" class="size-6" />
                </button>
            </Tooltip>
        </template>

        <DropdownMenuItem :href="'/exports/' + project.id + '/html'" icon="html-file" :download="project.slug + '-' + new Date().toLocaleDateString() + '.spectacular.html'">HTML</DropdownMenuItem>
        <DropdownMenuItem :href="'/exports/' + project.id + '/markdown'" icon="markdown-file" :download="project.slug + '-' + new Date().toLocaleDateString() + '.spectacular.md'">Markdown</DropdownMenuItem>
        <DropdownMenuItem :href="'/exports/' + project.id + '/json'" icon="json-file" :download="project.slug + '-' + new Date().toLocaleDateString() + '.spectacular.json'">JSON</DropdownMenuItem>
    </DropdownMenu>

    <slot name="after" />
</template>

<script>
import DropdownMenu from '@/components/DropdownMenu.vue';
import DropdownMenuItem from '@/components/DropdownMenuItem.vue';
import IconSet from '@/components/IconSet.vue';
import NavbarButton from '@/components/NavbarButton.vue';
import Tooltip from '@/components/Tooltip.vue';

export default {
    components: {
        DropdownMenu,
        DropdownMenuItem,
        IconSet,
        NavbarButton,
        Tooltip,
    },
    props: ['project'],
};
</script>
