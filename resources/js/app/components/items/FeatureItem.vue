<template>
    <section
        v-if="!is_filtered && !is_empty"
        :id="'feature_' + feature.id"
        class="mb-4 bg-white p-8 py-4 shadow rounded-3xl print:p-0 print:shadow-none outline-2 duration-500 dark:bg-gray-900"
        :class="is_active || highlight ? 'outline-offset-3 outline-gray-800 dark:outline-gray-200' : 'outline-transparent'">

        <FeatureToolbar :feature />

        <h3 class="font-semibold text-2xl mb-2"><a :href="'#feature_' + feature.id">{{ feature.name }}</a></h3>
        
        <RichText :markup="feature.description" class="mb-4" v-if="feature.description" />
        <p class="italic text-gray-400 mb-4 dark:text-gray-500" v-else>{{ $t('No description', feature.project.locale) }}</p>

        <div class="space-y-8 mt-6">
            <RequirementItem :requirement="requirement" v-for="requirement in requirements" :key="requirement.id" />
        </div>
    </section>
</template>

<script>
import FeatureToolbar from '@/components/navigation/FeatureToolbar.vue';
import RichText from '@/components/RichText.vue';
import RequirementItem from '@/components/items/RequirementItem.vue';

export default {
    components: {
        FeatureToolbar,
        RichText,
        RequirementItem
    },
    computed: {
        project_has_users() {
            return !!this.feature.project.actors.isNotEmpty();
        },
        is_active() {
            return this.$route.params.feature_id === this.feature.id;
        },
        is_filtered() {
            return this.feature.project.filters.has_features
                && this.feature.project.filters.features.includes(this.feature.id) === this.feature.project.filters.exclude_features;
        },
        is_empty() {
            return this.feature.project.filters.hide_empty_features
                && this.feature.requirements.isNotEmpty()
                && this.feature.requirements.every(requirement => requirement.is_filtered);
        },
        requirements() {
            return this.feature.requirements.sortBy('id').sortBy('weight');
        },
    },
    data() {
        return {
            'highlight': false,
        };
    },
    mounted() { 
        if (this.is_active) {
            document.getElementById('feature_' + this.feature.id).scrollIntoView(true);
        }
        
        if (this.feature.was_recently_created) {
            this.highlight = true;

            setTimeout(() => {
                this.highlight = false;
            }, 3000);
        }
    },
    props: [
        'feature'
    ],
};
</script>
