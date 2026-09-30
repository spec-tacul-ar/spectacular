<template>
    <div ref="menu" class="relative flex print:hidden" :class="{ 'z-20': is_open }">
        <slot name="trigger" :toggle="toggle" :is-open="is_open">
            <button type="button" class="block p-2 -mr-2" @click="toggle">
                <IconSet name="mini-menu" />
            </button>
        </slot>

        <Transition
            name="custom-classes"
            enter-from-class="opacity-0 scale-95"
            enter-active-class="origin-top-right motion-reduce:transition-none transition"
            leave-active-class="origin-top-right motion-reduce:transition-none transition"
            leave-to-class="opacity-0 scale-95">

            <div
                v-if="is_open"
                class="absolute -bottom-2 right-0 translate-y-full bg-white text-gray-800 shadow-lg border border-gray-400 rounded-lg w-56 py-2 z-10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                
                <div v-if="heading" class="uppercase font-semibold text-xs text-gray-400 text-center px-4 border-b border-b-gray-400 pb-2 mb-2 dark:border-b-gray-700 dark:text-gray-500">{{ heading }}</div>

                <slot />
            </div>
        </Transition>
    </div>
</template>

<script>
import { createFocusTrap } from 'focus-trap';
import IconSet from '@/components/IconSet.vue';

export default {
    beforeUnmount() {
        if (this.trap) {
            this.trap.deactivate({ onPostDeactivate: null });
        }
    },
    components: {
        IconSet,
    },
    data() {
        return {
            is_open: false,
            trap: null,
        };
    },
    methods: {
        close() {
            if (this.trap) {
                this.trap.deactivate();
            }
        },
        open() {
            this.is_open = true;

            this.$nextTick(() => {
                this.trap = createFocusTrap(this.$refs.menu, {
                    clickOutsideDeactivates: true,
                    onPostDeactivate: () => this.is_open = false,
                }).activate();
            });
        },
        toggle() {
            if (this.is_open) {
                this.close();
                return;
            }

            this.open();
        },
    },
    props: [
        'heading'
    ],
    watch: {
        '$route': {
            handler() {
                if (this.trap) {
                    this.trap.deactivate();
                }
            },
        },
    },
};
</script>
