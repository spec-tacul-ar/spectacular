<template>
    <article
        v-if="!this.requirement.is_filtered" :id="'requirement_' + requirement.id"
        class="relative" :class="{ 'opacity-75': requirement.is_draft }">

        <div class="absolute -top-4 right-10 flex gap-2">
            <span class="bg-gray-400 text-white rounded-full px-4 py-1 dark:bg-gray-700 dark:text-gray-100" v-if="requirement.is_draft">{{ $t('Draft', project.locale) }}</span>
            <span class="bg-red-400 text-white rounded-full px-4 py-1 dark:bg-red-950 dark:text-red-100" v-else-if="requirement.is_blocked">{{ $t('Blocked', project.locale) }}</span>
            <span class="bg-green-400 text-white rounded-full px-4 py-1 dark:bg-green-950 dark:text-green-100" v-else-if="requirement.is_complete">{{ $t('Complete', project.locale) }}</span>
        </div>

        <div
            class="rounded-xl border border-gray-200 -mx-4 outline-2 duration-500 dark:border-gray-800"
            :class="is_active || highlight ? 'outline-offset-3 outline-gray-800 dark:outline-gray-200' : 'outline-transparent'">

            <div class="flex items-center gap-4 bg-gray-200 rounded-t-xl p-4 py-5 dark:bg-gray-800">
                <h1 class="text-xl font-semibold mr-auto"><a :href="'#requirement_' + requirement.id">{{ requirement.title }}</a></h1>

                <RequirementToolbar :requirement />
            </div>

            <div class="bg-white space-y-4 py-4 dark:bg-gray-900">
                <div class="bg-red-400 text-white flex items-center justify-between px-4 py-2 dark:bg-red-950 dark:text-red-100" v-if="requirement.is_blocked">
                    <p>{{ requirement.blocked_reason }}</p>

                    <DropdownMenu v-if="project.can_write" ref="dropdown">
                        <DropdownMenuItem icon="unblock" :loading="is_waiting_for_unblock" @click.stop="unblock()">Unblock</DropdownMenuItem>
                    </DropdownMenu>
                </div>

                <div class="px-4">
                    <RichText v-if="requirement.description" :markup="requirement.description" />
                    <p v-if="!requirement.description" class="italic text-gray-400 dark:text-gray-500">{{ $t('No description', project.locale) }}</p>
                </div>

                <div v-if="requirement.unknowns.isNotEmpty()" class="space-y-2">
                    <UnknownItem :unknown="unknown" v-for="unknown in requirement.unknowns" :key="unknown.id" />
                </div>

                <p v-if="requirement.source" class="px-4"><strong>{{ $t('Source', project.locale) }}:</strong> {{ requirement.source }}</p>
            </div>

            <ul class="border-t border-gray-200 divide-y divide-gray-200 dark:border-gray-800 dark:divide-gray-800" v-if="requirement.tasks.isNotEmpty()">
                <TaskItem :task="task" v-for="task in requirement.tasks.sortBy('weight')" :key="task.id" />
            </ul>
            
            <div class="flex justify-between items-center bg-gray-200 rounded-b-xl px-4 py-2 dark:bg-gray-800">
                <div class="text-sm text-gray-400 dark:text-gray-500"><strong>{{ $t('Ref', project.locale) }}:</strong> {{ requirement.reference }}</div>
            </div>
        </div>
    </article>
</template>

<script>
import DropdownMenu from '@/components/DropdownMenu.vue';
import DropdownMenuItem from '@/components/DropdownMenuItem.vue';
import RequirementToolbar from '@/components/navigation/RequirementToolbar.vue';
import RichText from '@/components/RichText.vue';
import UnknownItem from '@/components/items/UnknownItem.vue';
import TaskItem from '@/components/items/TaskItem.vue';
import Requirement from '@/stores/models/Requirement';
import { useAlertsStore } from '@/stores';

export default {
    inject: ['api', 'project'],
    components: {
        DropdownMenu,
        DropdownMenuItem,
        RequirementToolbar,
        RichText,
        UnknownItem,
        TaskItem
    },
    computed: {
        is_active() {
            return this.$route.params.requirement_id === this.requirement.id;
        },
    },
    data() {
        return {
            'highlight': false,
            'is_waiting_for_unblock': false,
        };
    },
    methods: {
        unblock() {
            this.is_waiting_for_unblock = true;

            this.api.post('requirements/' + this.requirement.id + '/unblock')
                .then((result) => {
                    Requirement.repository().save(result.data);

                    useAlertsStore().push('Requirement unblocked.');

                    this.$refs.dropdown.close();
                })
                .finally(() => this.is_waiting_for_unblock = false);
        },
    },
    mounted() {
        if (this.is_active) {
            document.getElementById('requirement_' + this.requirement.id).scrollIntoView(true);
        }  

        if (this.requirement.was_recently_created) {
            this.highlight = true;

            setTimeout(() => {
                this.highlight = false;
            }, 3000);
        }
    },
    props: [
        'requirement'
    ],
};
</script>
