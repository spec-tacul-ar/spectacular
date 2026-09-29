<template>
    <div
        class="relative flex flex-wrap gap-4 transition-all duration-500"
        :class="project.archived_at ? 'items-center px-4 py-3' : 'items-start p-4'">

        <div>
            <RouterLink
                :to="{name: 'projects.show', params: { project_id: project.id }}"
                class="font-semibold hover:underline after:absolute after:inset-0"
                :class="project.archived_at ? 'text-base' : 'text-2xl'">

                {{ project.name }}
            </RouterLink>
        </div>

        <div class="relative z-10 ml-auto text-right">
            <div class="flex justify-end gap-1">
                <div v-if="!project.archived_at && project.my_role_name" class="inline-flex gap-2 leading-none px-2 h-6 text-sm rounded-full items-center bg-gray-200 text-gray-500 cursor-default dark:bg-gray-800 dark:text-gray-300">
                    {{ project.my_role_name }}
                </div>

                <Tooltip v-if="project.collaborations_count > 0 && !project.archived_at" text="Collaborations">
                    <div class="inline-flex gap-2 leading-none px-1 pr-2 h-6 text-sm rounded-full items-center bg-gray-200 text-gray-500 cursor-default dark:bg-gray-800 dark:text-gray-300">
                        <IconSet name="person-circle" /> {{ project.collaborations_count }}
                    </div>
                </Tooltip>

                <template v-if="project.requirements_count && !project.archived_at">
                    <Tooltip v-if="project.non_draft_requirements_count > 0" text="Completed">
                        <div class="inline-flex gap-2 leading-none px-1 pr-2 h-6 text-sm rounded-full items-center bg-green-100 text-green-500 cursor-default dark:bg-green-950 dark:text-green-300">
                            <IconSet name="success" /> {{ Math.round((project.completed_requirements_count / project.non_draft_requirements_count) * 100) }}%
                        </div>
                    </Tooltip>

                    <Tooltip v-if="project.unknowns_count > 0" text="Unknowns">
                        <div class="inline-flex gap-2 leading-none px-1 pr-2 h-6 text-sm rounded-full items-center bg-yellow-100 text-yellow-600 cursor-default dark:bg-yellow-950 dark:text-yellow-300">
                            <IconSet name="question" /> {{ project.unknowns_count }}
                        </div>
                    </Tooltip>

                    <Tooltip v-if="project.blocked_requirements_count > 0" text="Blocked">
                        <div class="inline-flex gap-2 leading-none px-1 pr-2 h-6 text-sm rounded-full items-center bg-red-100 text-red-500 cursor-default dark:bg-red-950 dark:text-red-300">
                            <IconSet name="warning" /> {{ project.blocked_requirements_count }}
                        </div>
                    </Tooltip>
                </template>

                <p v-if="project.archived_at" class="text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">Archived {{ archived_at }}</p>
            </div>

            <p v-if="!project.archived_at" class="text-sm text-gray-500 mt-2 dark:text-gray-400">Updated {{ updated_at }} ago</p>
        </div>
    </div>
</template>

<script>
import IconSet from '@/components/IconSet.vue';
import Tooltip from '@/components/Tooltip.vue';
import { format, formatDistance } from 'date-fns';

export default {
    components: {
        IconSet,
        Tooltip,
    },
    computed: {
        archived_at() {
            return format(Date.parse(this.project.archived_at), 'PP');
        },
        updated_at() {
            return formatDistance(this.project.updated_at, new Date());
        },
    },
    props: [
        'project'
    ],
};
</script>
