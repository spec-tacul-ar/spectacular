<template>
    <div class="mt-4">
        <div class="border-b border-gray-200 mx-4 pb-4 mb-4 dark:border-gray-800">
            <div class="flex items-center justify-between py-2">
                <label class="flex items-center gap-2">
                    <input v-model="filters.hide_empty_features" type="checkbox">
                    Hide empty
                </label>

                <InfoPopover small>
                    <p>Hide any features that have no visible requirements because of filtering.</p>
                </InfoPopover>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-2 mx-4 mb-2">
                <h4 class="uppercase font-semibold mr-auto">Statuses</h4>

                <button
                    v-if="filters.has_statuses"
                    type="button"
                    class="p-1"
                    @click="filters.clearStatuses()">

                    <IconSet name="x-lg" class="size-4" />
                </button>
            </div>

            <div class="mb-4">
                <FilterSwitch
                    :value="filters.statuses.draft"
                    @change="filters.setFilter('statuses', 'draft', $event)">

                    Draft
                </FilterSwitch>

                <FilterSwitch
                    :value="filters.statuses.completed"
                    @change="filters.setFilter('statuses', 'completed', $event)">

                    Completed
                </FilterSwitch>

                <FilterSwitch
                    :value="filters.statuses.blocked"
                    @change="filters.setFilter('statuses', 'blocked', $event)">

                    Blocked
                </FilterSwitch>

                <FilterSwitch
                    :value="filters.statuses.has_tasks"
                    @change="filters.setFilter('statuses', 'has_tasks', $event)">

                    Has tasks
                </FilterSwitch>

                <FilterSwitch
                    :value="filters.statuses.has_unknowns"
                    @change="filters.setFilter('statuses', 'has_unknowns', $event)">

                    Has unknowns
                </FilterSwitch>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-2 mx-4 mb-2">
                <h4 class="uppercase font-semibold mr-auto">Users</h4>

                <button
                    v-if="filters.has_actors"
                    type="button"
                    class="p-1"
                    @click="filters.clearActors()">

                    <IconSet name="x-lg" class="size-4" />
                </button>
            </div>

            <div class="mb-4">
                <FilterSwitch
                    v-for="actor in project.actors.sortBy('weight')"
                    :key="actor.id"
                    :value="filters.actors[actor.id]"
                    @change="filters.setFilter('actors', actor.id, $event)">

                    {{ actor.name }}
                </FilterSwitch>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-2 mx-4 mb-2">
                <h4 class="uppercase font-semibold mr-auto">Features</h4>

                <button
                    v-if="filters.has_features"
                    type="button"
                    class="p-1"
                    @click="filters.toggleFeatureMode()">

                    <IconSet name="plus-slash-minus" class="size-4" />
                </button>

                <button
                    v-if="filters.has_features"
                    type="button"
                    class="p-1"
                    @click="filters.clearFeatures()">

                    <IconSet name="x-lg" class="size-4" />
                </button>
            </div>

            <div class="mb-4">
                <button
                    v-for="feature in project.features.sortBy('weight')"
                    :key="feature.id"
                    type="button"
                    class="w-full flex items-center gap-2 text-left hover:bg-gray-50 px-4 py-2 dark:hover:bg-gray-800"
                    :class="filters.features.includes(feature.id) ? '' : 'pl-10'"
                    @click="filters.toggleFeature(feature.id)">

                    <IconSet v-if="filters.features.includes(feature.id) && !filters.exclude_features" name="plus-lg" class="size-4 shrink-0" />
                    <IconSet v-if="filters.features.includes(feature.id) && filters.exclude_features" name="minus-lg" class="size-4 shrink-0" />

                    {{ feature.name }}
                </button>
            </div>
        </div>

    </div>
</template>

<script>
import FilterSwitch from '@/components/FilterSwitch.vue';
import IconSet from '@/components/IconSet.vue';
import InfoPopover from '@/components/InfoPopover.vue';

export default {
    components: {
        FilterSwitch,
        IconSet,
        InfoPopover,
    },
    computed: {
        filters() {
            return this.project.filters;
        },
    },
    props: ['project'],
};
</script>
