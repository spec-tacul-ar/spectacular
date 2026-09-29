<template>
    <div class="flex items-center print:hidden">
        <RouterLink v-if="project.can_write" :to="{ name: 'projects.requirements.edit', params: { requirement_id: requirement.id }}" class="p-2">
            <IconSet name="edit" />
        </RouterLink>

        <DropdownMenu>
            <DropdownMenuItem v-if="project.can_write && !requirement.is_draft && !requirement.is_blocked && !requirement.is_complete" type="button" :loading="is_waiting" icon="check-lg" @click.stop="markComplete()">Mark complete</DropdownMenuItem>
            <DropdownMenuItem v-if="project.can_write && requirement.is_complete" type="button" :loading="is_waiting" icon="x-lg" @click.stop="reopen()">Reopen</DropdownMenuItem>
            <DropdownMenuItem v-if="project.can_write && requirement.is_blocked" type="button" :loading="is_waiting" icon="unblock" @click.stop="unblock()">Unblock</DropdownMenuItem>
            <DropdownMenuItem :to="{ name: 'projects.requirements.feedback', params: { requirement_id: requirement.id }}" icon="feedback">Feedback</DropdownMenuItem>
                        
            <slot name="menu" />
            
            <DropdownMenuItem v-if="project.can_write" @click="openRequirementDeleteModal" icon="trash" class="dropdown-item" danger>Delete</DropdownMenuItem>
        </DropdownMenu>
    </div>
</template>

<script>
import DropdownMenu from '@/components/DropdownMenu.vue';
import DropdownMenuItem from '@/components/DropdownMenuItem.vue';
import RequirementDelete from '@/components/modals/RequirementDelete.vue';
import IconSet from '@/components/IconSet.vue';
import Requirement from '@/stores/models/Requirement';
import { useAlertsStore, useModalStore } from '@/stores';

export default {
    inject: ['api', 'project'],
    components: {
        DropdownMenu,
        DropdownMenuItem,
        IconSet,
    },
    data() {
        return {
            'is_waiting': false
        };
    },
    methods: {
        markComplete() {
            this.is_waiting = true;

            this.api.post('requirements/' + this.requirement.id + '/complete')
                .then((result) => {
                    Requirement.repository().save(result.data);

                    useAlertsStore().push('Requirement completed.');
                })
                .finally(() => this.is_waiting = false);
        },
        openRequirementDeleteModal() {
            useModalStore().open(RequirementDelete, {requirement: this.requirement});
        },
        reopen() {
            this.is_waiting = true;

            this.api.post('requirements/' + this.requirement.id + '/reopen')
                .then((result) => {
                    Requirement.repository().save(result.data);

                    useAlertsStore().push('Requirement reopened.');
                })
                .finally(() => this.is_waiting = false);
        },
        unblock() {
            this.is_waiting = true;

            this.api.post('requirements/' + this.requirement.id + '/unblock')
                .then((result) => {
                    Requirement.repository().save(result.data);

                    useAlertsStore().push('Requirement unblocked.');
                })
                .finally(() => this.is_waiting = false);
        }
    },
    props: ['requirement'],
};
</script>
