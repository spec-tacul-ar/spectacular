<template>
    <ol class="p-4 space-y-4">
        <li><a href="#introduction" class="uppercase font-semibold" @click.prevent="scroll">Introduction</a></li>
        <li><a href="#users" class="uppercase font-semibold" @click.prevent="scroll">{{ $t('Users', project.locale) }}</a></li>
        <li><a href="#features" class="uppercase font-semibold" @click.prevent="scroll">{{ $t('Features', project.locale) }}</a></li>
        <li v-for="feature in this.project.features.sortBy('weight')" :key="feature.id">
            <a :href="'#feature_' + feature.id" class="block uppercase mb-3" @click.prevent="scroll">{{ feature.name }}</a>

            <ol class="space-y-4">
                <li v-for="requirement in sortRequirements(feature.requirements)" :key="requirement.id" class="flex items-center gap-2">
                    <div v-if="requirement.is_draft" class="size-2 shrink-0 rounded-full bg-gray-400 dark:bg-gray-500" title="Draft" role="img" aria-label="Draft"></div>
                    <div v-else-if="requirement.is_blocked" class="size-2 shrink-0 rounded-full bg-red-400 dark:bg-red-950" title="Blocked" role="img" aria-label="Blocked"></div>
                    <div v-else-if="requirement.is_complete" class="size-2 shrink-0 rounded-full bg-green-400 dark:bg-green-500" title="Complete" role="img" aria-label="Complete"></div>
                    <div v-else-if="requirement.unknowns.isNotEmpty()" class="size-2 shrink-0 rounded-full bg-orange-400" title="Has unknowns" role="img" aria-label="Has unknowns"></div>
                    <div v-else class="size-2 shrink-0"></div>

                    <a :href="'#requirement_' + requirement.id" class="text-sm leading-tight" :class="{ 'opacity-75': requirement.is_draft }" @click.prevent="scroll">{{ formatName(requirement.name) }}</a>
                </li>
            </ol>
        </li>
    </ol>
</template>

<script>
import collect from 'collect.js';

export default {
    methods: {
        formatName: name => name.charAt(0).toUpperCase() + name.substring(1),
        sortRequirements: requirements => collect(requirements).sortBy('weight'),
        scroll(event) {
            const href = event.currentTarget.getAttribute('href');
            const hash = href.split('#')[1];

            const element = document.getElementById(hash);

            if (element) {
                window.location.hash = hash; // history.replaceState() upsets Vue Router
            
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });

                this.$emit('navigation');
            }
        },
    },
    props: ['project']
};
</script>
