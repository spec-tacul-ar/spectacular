<template>
    <SidepanelLayout help="/docs/requirements">
        <template #toolbar>
            <button v-if="requirement_id" type="button" class="hidden sm:block p-2" @click="scrollToRequirement()">
                <IconSet name="scroll-to" class="size-6" />
            </button>

            <EditingWarning :editors="other_editors" class="ml-auto" />
        </template>

        <template #buttons>
            <div class="flex items-center gap-4">
                <SpinnerButton type="submit" class="btn btn-primary" :loading="is_waiting" @click="submit">Save</SpinnerButton>
                <FormInput v-if="is_creating" type="checkbox" :id="elementId('add_another')" label="Create another after saving" v-model="add_another" />
            </div>
        </template>

        <form @submit.prevent="submit">
            <h2 class="text-4xl font-light mb-4">
                {{ is_creating ? 'Create requirement' : 'Edit requirement' }}
            </h2>

            <ValidationMessages :errors="errors" />

            <p class="mb-4">Requirements are the core of your specification. They define the functionality that is expected from the system. You can also use this space to define non-functional requirements such as which icons to use.</p>
            <p class="mb-4">How granlar you are with your requirements is up to you but a general rule of thumb is that a developer should be able to implement a requirement within a day.</p>

            <div v-if="!requirement && !feature_id" class="mb-4">
                <FormOptions type="select" :id="elementId('feature_id')" label="Feature" v-model="form.feature_id" :options="features" :error="errors.feature_id" required />
            </div>

            <div class="mb-4">
                <div class="flex w-full">
                    <FormLabel :for="elementId('name')" required>Title</FormLabel>

                    <InfoPopover>
                        <p class="font-semibold mb-4">This succinctly describes what can be achieved thanks to this requirement.</p>
                        <p class="mb-4">Examples:</p>
                        <ul class="list-disc italic ml-6 mb-4">
                            <li><span>Visitors can</span> see the latest news items on the homepage.</li>
                            <li><span>Staff can</span> submit new sales reports.</li>
                            <li><span>Managers and Supervisors can</span> compile a roster of volunteers.</li>
                        </ul>
                        <p class="mb-8">Generally, a motivation is not required. This can be included in the description below if you like.</p>

                        <p class="font-semibold mb-4">You can specify which types of users are able to utilise the functionality defined in this requirement.</p>
                        <p class="mb-4">For instance, you may want to make it clear that only administrators are allowed to ban users.</p>
                        <p class="list-disc italic"><strong>Tip:</strong><br>If you find yourself adding too many users, you might want to think about creating a new user that encompasses others.</p>
                    </InfoPopover>
                </div>

                <div v-if="actors.length > 0" class="mb-2">
                    <FormOptions type="checkbox" :id="elementId('actor_ids')" v-model="form.actor_ids" :options="actors" :error="errors.actor_ids" inline />
                </div>

                <label
                    class="form-control flex items-center overflow-hidden p-0 focus-within:border-gray-800 focus-within:ring-1 focus-within:ring-gray-800 dark:focus-within:border-gray-400 dark:focus-within:ring-gray-400"
                    :class="errors.name ? 'border-red-400 focus-within:border-red-400 focus-within:ring-red-400 dark:border-red-800 dark:focus-within:border-red-800 dark:focus-within:ring-red-800' : ''">

                    <span
                        aria-hidden="true"
                        class="pointer-events-none shrink-0 whitespace-nowrap pl-3">

                        {{ name_prefix }}
                    </span>

                    <input type="text"
                        :id="elementId('name')"
                        class="min-w-0 grow border-0 bg-transparent py-2 pl-2 pr-3 focus:ring-0 dark:bg-transparent"
                        required
                        v-model="form.name" />
                </label>
            </div>
            
            <div class="mb-4">
                <FormRichText :id="elementId('description')" label="Description" :heading="5" :error="errors.description" v-model="form.description">
                    <template v-slot:help>
                        <p class="font-semibold mb-4">Use this field to give more detail about this requirement and an acceptance criteria.</p>
                        <p class="mb-4">Try to include:</p>
                        <ul class="list-disc italic ml-6 mb-4">
                            <li>Motivation for why the user might need this ability.</li>
                            <li>Extra details about how the use interacts with this part of the system.</li>
                            <li>Define functionality you wish to exclude from the requirements. For instance, you might want to make it clear that the user login requirement does not include a <em>remember me</em> function.</li>
                        </ul>
                    </template>
                </FormRichText>
            </div>

            <div class="mb-4">
                <FormInput type="text" :options="sources" :id="elementId('source')" label="Source" :error="errors.source" v-model="form.source">
                    <template v-slot:help>
                        <p class="font-semibold mb-4">Where this requirement came from.</p>
                        <p class="mb-4">This is particularly useful when someone queries how something got into the specification. Be sure to keep adding to it as more information comes in.</p>
                        <p>Examples:</p>
                        <ul class="list-disc italic ml-6">
                            <li>Project kickoff meeting</li>
                            <li>Email from Jane, 14th Jan 2022</li>
                            <li>Focus group notes</li>
                        </ul>
                    </template>
                </FormInput>
            </div>

            <div class="border border-gray-100 mb-4 dark:border-gray-800">
                <div class="bg-gray-100 px-4 py-2 dark:bg-gray-900">
                    <FormInput type="checkbox" :id="elementId('is_blocked')" label="This requirement is blocked." :error="errors.is_blocked" v-model="form.is_blocked">
                        <template v-slot:help>
                            <p class="font-semibold mb-4">Check this if something is holding up the completion of the requirement.</p>
                            <p class="mb-4">Describe the reason why this requirement cannot be met at this time.</p>
                            <p>Examples:</p>
                            <ul class="list-disc italic ml-6">
                                <li>API keys provided by client do not work.</li>
                                <li>Waiting product CSV before proceeding.</li>
                                <li>Need further clarification on questions below.</li>
                            </ul>
                        </template>
                    </FormInput>
                </div>
                <div class="bg-white p-4 dark:bg-gray-950" v-if="form.is_blocked">
                    <FormInput type="text" :id="elementId('blocked_reason')" label="The reason this requirement is blocked" :error="errors.blocked_reason" v-model="form.blocked_reason" :required="form.is_blocked" />
                </div>
            </div>

            <fieldset class="mb-4">
                <legend class="text-xl mb-2">Tasks</legend>

                <p class="mb-4">Tasks are the real steps needed to complete the requirement.</p>

                <Draggable 
                    v-model="form.tasks" 
                    group="tasks" 
                    item-key="id"
                    tag="div"
                    handle=".handle"
                    class="space-y-4">

                    <template #item="{index, element}">
                        <div class="border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex">
                                <div class="px-4 py-1 bg-gray-200 cursor-grab rounded-br-xl dark:bg-gray-800"><IconSet name="grip-horizontal" class="size-4 handle" /></div>
                                <button type="button" class="ml-auto px-4 py-1 bg-red-400 rounded-bl-xl text-white dark:bg-red-950 dark:text-red-100 dark:hover:bg-red-900" v-on:click="removeTask(index)"><IconSet name="x-lg" /></button>
                            </div>

                            <div class="p-4">
                                <div class="mb-4">
                                    <FormInput type="text" label="Name" :id="elementId('task_name_' + index)" v-model="element.name" required>
                                        <template v-slot:help v-if="index === 0">
                                            <p>Examples:</p>
                                            <ul class="list-disc italic ml-6">
                                                <li>Create a new database.</li>
                                                <li>Import existing users data the new database.</li>
                                                <li>Point the legacy systems to the new database.</li>
                                            </ul>
                                        </template>
                                    </FormInput>
                                </div>
                                <div>
                                    <FormInput type="checkbox" label="Task is complete" :id="elementId('task_is_complete_' + index)" v-model="element.is_complete" />
                                </div>
                            </div>
                        </div>
                    </template>
                </Draggable>

                <button type="button" class="btn btn-primary btn-sm mt-2" v-on:click="addTask()">Add task</button>
            </fieldset>

            <fieldset>
                <legend class="text-xl mb-2">Unknowns</legend>

                <p class="mb-4">Unknowns are for documenting ambiguities that should be resolved before the work can commence.</p>

                <div v-if="form.unknowns.length > 0">
                    <div class="flex">
                        <label :for="elementId('Unknown_name_0')" class="font-bold mr-auto mb-1">Question&nbsp;<span class="text-red-400">*</span></label>

                        <InfoPopover>
                            <p>Examples:</p>
                            <ul class="list-disc italic ml-6">
                                <li>How long should it wait until a reminder notification is sent?</li>
                                <li>What happens if the user is not authenticated for this action?</li>
                                <li>Is there a limit to the number of seats a customer can reserve?</li>
                            </ul>
                        </InfoPopover>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center gap-2" v-for="(unknown, index) in form.unknowns" :key="unknown.id">
                        <div class="w-full">
                            <FormInput type="text" :id="elementId('unknown_name_' + index)" v-model="unknown.name" required />
                        </div>
                        <button type="button" class="text-red-400 dark:text-red-300" v-on:click="removeUnknown(index)"><IconSet name="x" class="size-6" /></button>
                    </div>
                </div>

                <button type="button" class="btn btn-primary btn-sm mt-2" v-on:click="addUnknown()">{{ form.unknowns.length ? 'Add another unknown' : 'Add unknown' }}</button>
            </fieldset>
            <div class="mt-6">
                <FormInput type="checkbox" :id="elementId('is_draft')" label="This requirement is a draft." :error="errors.is_draft" v-model="form.is_draft" :disabled="!form.is_draft && !requirement?.is_draft && is_complete">
                    <template #help>
                        <p>Use drafts for requirements that need more detail or belong to a later phase.</p>
                    </template>
                </FormInput>
            </div>
        </form>
    </SidepanelLayout>
</template>

<script>
import Draggable from 'vuedraggable';
import EditingWarning from '@/components/EditingWarning.vue';
import FormInput from '@/components/forms/FormInput.vue';
import FormLabel from '@/components/forms/FormLabel.vue';
import FormRichText from '@/components/forms/FormRichText.vue';
import FormOptions from '@/components/forms/FormOptions.vue';
import IconSet from '@/components/IconSet.vue';
import InfoPopover from '@/components/InfoPopover.vue';
import KeyboardShortcuts from '@/mixins/KeyboardShortcuts';
import EditingPresence from '@/mixins/EditingPresence';
import SidepanelLayout from '@/components/layouts/SidepanelLayout.vue';
import SpinnerButton from '@/components/SpinnerButton.vue';
import TrackDirty from '@/mixins/TrackDirty';
import UniqueId from '@/mixins/UniqueId';
import ValidationMessages from '@/components/ValidationMessages.vue';
import Assignment from '@/stores/models/Assignment';
import Requirement from '@/stores/models/Requirement';
import Task from '@/stores/models/Task';
import Unknown from '@/stores/models/Unknown';
import { useAlertsStore } from '@/stores';
import { inject } from 'vue';

export default {
    inject: ['api'],
    beforeRouteLeave() {
        return !this.is_dirty || this.confirmClose();
    },
    components: {
        Draggable,
        EditingWarning,
        FormInput,
        FormLabel,
        FormRichText,
        FormOptions,
        IconSet,
        InfoPopover,
        SidepanelLayout,
        SpinnerButton,
        ValidationMessages,
    },
    computed: {
        is_complete() {
            return this.requirement?.is_complete ?? false;
        },
        features() {
            return this.project.features.sortBy('weight').all();
        },
        is_creating() {
            return !this.requirement_id;
        },
        editing_channel() {
            return this.requirement_id ? 'requirements.editing.' + this.requirement_id : null;
        },
        name_prefix() {
            return (this.actors.length > 0 ? '…' : this.$t('Users', this.project.locale) + ' ') + this.$t('can', this.project.locale);
        },
        actors() {
            return this.project.actors.sortBy('weight').all();
        },
        sources() {
            // TODO Fix this
            return [];
        }
    },
    data() {
        const data = {
            add_another: false,
            form: {
                actor_ids: this.requirement ? this.requirement.assignments.all().map(assignment => assignment.actor_id) : [],
                blocked_reason: this.requirement?.blocked_reason,
                description: this.requirement?.description,
                feature_id: this.requirement?.feature_id ?? this.feature_id ?? null,
                is_blocked: this.requirement?.is_blocked,
                is_draft: this.requirement?.is_draft ?? false,
                name: this.requirement?.name,
                unknowns: this.requirement ? this.requirement.unknowns.all().map(unknown => ({ ...unknown })) : [],
                source: this.requirement?.source,
                tasks: this.requirement ? this.requirement.tasks.all().map((task) => ({ ...task })).sort((a, b) => (a.weight > b.weight) ? 1 : -1) : [],
            },
            deleted_unknown_id: [],
            deleted_task_ids: [],
            errors: {},
            is_waiting: false
        };

        if (!this.requirement && this.project.actors.count() === 1) {
            data.form.actor_ids.push(this.project.actors.first().id);
        }

        return data;
    },
    methods: {
        addUnknown() {
            this.form.unknowns.push({'name': ''});
        },
        addTask() {
            this.form.tasks.push({'name': '', 'is_complete': false});
        },
        removeUnknown(index) {
            if (this.form.unknowns[index].id) {
                this.deleted_unknown_id.push(this.form.unknowns[index].id);
            }

            this.form.unknowns.splice(index, 1);
        },
        removeTask(index) {
            if (this.form.tasks[index].id) {
                this.deleted_task_ids.push(this.form.tasks[index].id);
            }

            this.form.tasks.splice(index, 1);
        },
        scrollToRequirement() {
            document.getElementById('requirement_' + this.requirement_id).scrollIntoView(true);
        },
        submit() {
            this.errors = {};
            this.is_waiting = true;

            const endpoint = this.is_creating ? 'requirements' : 'requirements/' + this.requirement_id + '/edit';
            const data = this.is_creating ? {...this.form} : this.form;

            data.tasks.forEach((task, index) => task.weight = index);

            if (!data.is_blocked) {
                data.blocked_reason = null;
            }

            this.api.post(endpoint, data)
                .then((result) => {
                    const requirement = {...result.data, was_recently_created: this.is_creating};

                    if (this.is_creating) {
                        Requirement.repository().save(requirement);
                    } else {
                        Requirement.repository().save(requirement);
                    }

                    if (this.requirement) {
                        // TODO: Are these not working? deleted_unknown_id should be plural?
                        Unknown.repository().deleteMany(this.deleted_unknown_id);
                        Task.repository().deleteMany(this.deleted_task_ids);

                        const actor_ids = requirement.assignments.map(assignment => assignment.actor_id);
                        const deleted_assignments = this.requirement.assignments.whereNotIn('actor_id', actor_ids).pluck('id').all();
                        Assignment.repository().deleteMany(deleted_assignments);
                    }

                    useAlertsStore().push('Requirement saved.');

                    if (this.add_another) {
                        this.form.blocked_reason = '';
                        this.form.description = '';
                        this.form.is_blocked = false;
                        this.form.is_draft = false;
                        this.form.name = '';
                        this.form.unknowns = [];
                        this.form.source = '';
                        this.form.tasks = [];

                        document.getElementById('sidepanel').scrollTo(0, 0);
                    }

                    this.setCleanForm();

                    if (!this.add_another) {
                        this.$router.push({ name: 'projects.show', params: { project_id: this.project_id }, hash: '#requirement_' + requirement.id });
                    }
                })
                .catch(error => this.errors = error.body.errors ?? {})
                .finally(() => this.is_waiting = false);
        }
    },
    mixins: [
        EditingPresence,
        KeyboardShortcuts,
        TrackDirty,
        UniqueId
    ],
    mounted() {
        document.title = 'Create requirement';
    },
    props: {
        'feature_id': {
            'type': String,
            'required': false,
        },
        'requirement_id': {
            'type': String,
            'required': false,
        },
        'project_id': {
            'type': String,
            'required': true,
        },
    },
    async setup(props) {
        const api = inject('api');
        const repository = Requirement.repository();
        const projects = Requirement.repository('projects');
        
        let requirement = null;

        if (props.requirement_id) {
            requirement = repository.find(props.requirement_id);

            if (!requirement) {
                const result = await api.get('projects/' + props.project_id, {query: {'hydrate': true}});
                projects.save({...result.data, is_hydrated: true});
                requirement = repository.find(props.requirement_id);
            }
        }

        return {
            requirement,
            project: projects.find(props.project_id),
        };
    },
};
</script>
