<template>
    <DefaultLayout>
        <Announcements />

        <section>
            <div class="flex justify-between flex-wrap items-end gap-4 mb-4 sm:pl-4">
                <h1 class="font-display font-semibold text-4xl">Projects</h1>
                
                <SplitButton v-if="!is_loading_projects && projects.isNotEmpty()">
                    <template #button>
                        <RouterLink :to="{ name: 'projects.create' }" class="btn btn-primary rounded-r-none">Create project</RouterLink>
                    </template>

                    <DropdownMenuItem icon="plus-lg" :loading="is_creating_blank_project" :disabled="is_creating_blank_project || is_creating_demo_project" @click="createBlankProject">Blank project</DropdownMenuItem>
                    <DropdownMenuItem icon="plus-lg" :loading="is_creating_demo_project" :disabled="is_creating_blank_project || is_creating_demo_project" @click="createDemoProject">Lucia's Restaurant</DropdownMenuItem>
                </SplitButton>
            </div>

            <LoadingSpinner label="Loading projects" v-if="is_loading_projects" />

            <div
                v-if="!is_loading_projects && projects.isEmpty()"
                class="flex flex-col items-center gap-2 bg-white/25 border-2 border-gray-200 border-dashed rounded-2xl p-4 mb-4 dark:border-gray-700 dark:bg-gray-900/50">

                <IconSet name="create-project" class="size-16 text-gray-600 dark:text-gray-300" />

                <h2 class="font-semibold">Welcome to Spectacular!</h2>

                <p>Get started by creating your first project.</p>

                <div class="flex flex-wrap items-center justify-center gap-4">
                    <SplitButton>
                        <template #button>
                            <RouterLink :to="{ name: 'projects.create' }" class="btn btn-primary rounded-r-none">Create project</RouterLink>
                        </template>

                        <DropdownMenuItem icon="plus-lg" :loading="is_creating_blank_project" :disabled="is_creating_blank_project || is_creating_demo_project" @click="createBlankProject">Blank project</DropdownMenuItem>
                    </SplitButton>

                    <SpinnerButton
                        type="button"
                        class="btn btn-primary-outline"
                        :disabled="is_creating_blank_project"
                        :loading="is_creating_demo_project"
                        @click="createDemoProject">
                        Create demo project
                    </SpinnerButton>
                </div>
            </div>

            <Card v-if="invitations.isNotEmpty()" class="divide-y divide-gray-300 dark:divide-gray-800 mb-8">
                <InvitationItem v-for="invitation in invitations" :key="invitation.id" :invitation="invitation" />
            </Card>

            <!-- TODO Sorting -->
            <Card v-if="active_projects.isNotEmpty()" class="divide-y divide-gray-300 dark:divide-gray-800 mb-8">
                <ProjectItem v-for="project in active_projects" :key="project.id" :project="project" />
            </Card>

            <section v-if="archived_projects.isNotEmpty()">
                <h2 class="text-2xl mb-4 sm:pl-4">Archived projects</h2>

                <Card class="divide-y divide-gray-300 dark:divide-gray-800">
                    <ProjectItem v-for="project in archived_projects" :key="project.id" :project="project" />
                </Card>
            </section>
        </section>
    </DefaultLayout>
</template>

<script>
import Announcements from '@/components/Announcements.vue';
import Card from '@/components/Card.vue';
import DefaultLayout from '@/components/layouts/DefaultLayout.vue';
import DropdownMenuItem from '@/components/DropdownMenuItem.vue';
import IconSet from '@/components/IconSet.vue';
import Invitation from '@/stores/models/Invitation';
import InvitationItem from '@/components/items/InvitationItem.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import ProjectItem from '@/components/items/ProjectItem.vue';
import Project from '@/stores/models/Project';
import SplitButton from '@/components/SplitButton.vue';
import SpinnerButton from '@/components/SpinnerButton.vue';
import { useAuthStore } from '@/stores';

export default {
    components: {
        Announcements,
        Card,
        DefaultLayout,
        DropdownMenuItem,
        IconSet,
        InvitationItem,
        LoadingSpinner,
        ProjectItem,
        SplitButton,
        SpinnerButton,
    },
    data() {
        return {
            is_creating_blank_project: false,
            is_creating_demo_project: false,
            is_loading_projects: false,
        };
    },
    computed: {
        active_projects() {
            return this.projects.whereNull('archived_at');
        },
        archived_projects() {
            return this.projects.whereNotNull('archived_at');
        },
        invitations() {
            return Invitation.repository().collection.where('email', useAuthStore().account.email).sortBy('name');
        },
        projects() {
            return Project.repository().collection.sortBy(project => project.name.toLocaleLowerCase());
        },
    },
    mounted() {
        // Projects
        if (this.projects.isEmpty()) {
            this.is_loading_projects = true;
        }

        this.api.get('projects')
            .then((result) => {
                Project.repository().saveMany(result.data);
            })
            .finally(() => this.is_loading_projects = false);

        this.api.get('invitations')
            .then((result) => {
                Invitation.repository().saveMany(result.data);
            });
    },
    methods: {
        createBlankProject() {
            this.is_creating_blank_project = true;

            this.api.post('projects', { name: 'Blank project' })
                .then((result) => {
                    const project = result.data;

                    Project.repository().save(project);

                    this.$router.push({ name: 'projects.show', params: { project_id: project.id } });
                })
                .finally(() => this.is_creating_blank_project = false);
        },
        createDemoProject() {
            this.is_creating_demo_project = true;

            this.api.post('projects/demo')
                .then((result) => {
                    const project = result.data;

                    Project.repository().save(project);

                    this.$router.push({ name: 'projects.show', params: { project_id: project.id } });
                })
                .finally(() => this.is_creating_demo_project = false);
        },
    },
    inject: ['api'],
};
</script>
