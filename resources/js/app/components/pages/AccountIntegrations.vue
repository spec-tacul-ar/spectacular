<template>
    <DefaultLayout>
        <template v-slot:menu>
            <div class="w-full flex items-center gap-2">
                <RouterLink :to="{ name: 'projects.index' }" class="link flex items-center gap-2">
                    <IconSet name="chevron-left" />

                    Dashboard
                </RouterLink>
            </div>
        </template>

        <h1 class="font-display font-semibold text-4xl text-center mb-4 mx-6">Integrations</h1>

        <Card class="p-6 mb-6 border-2 border-gray-800 dark:border-gray-400">
            <h2 class="font-display text-2xl mb-2">Connect an AI coding agent</h2>

            <p class="mb-4">Use the URL below to connect Spectacular to your AI coding agent via <a href="https://modelcontextprotocol.io/docs/2026-07-28/develop/connect-remote-servers" class="link">MCP</a>. You will be prompted to authenticate before access is granted.</p>

            <div class="border border-gray-200 p-4 dark:border-gray-800 dark:bg-gray-900">
                <code class="font-mono break-all text-sm">{{ mcp_endpoint }}</code>
            </div>
        </Card>

        <Card class="p-6">
            <h2 class="font-display text-2xl mb-4">Access tokens</h2>

            <div class="flex items-start gap-4 border-b border-gray-200 pb-6 mb-6 dark:border-gray-800">
                <p>This page lists active OAuth tokens that have been issued. For services that do not support the OAuth flow, you can create bearer tokens to use in Authorization headers.</p>

                <RouterLink :to="{ name: 'account.integrations.tokens.create' }" class="shrink-0 btn btn-primary"><IconSet name="plus-lg" /> Create new token</RouterLink>
            </div>

            <div v-if="tokens.isNotEmpty()" class="space-y-4 mb-2">
                <TokenItem v-for="token in tokens" :token :key="token.id" />
            </div>

            <LoadingSpinner v-if="tokens.isEmpty() && is_loading" label="Loading integrations" />

            <p v-if="tokens.isEmpty() && !is_loading" class="text-center text-gray-400 dark:text-gray-500">You do not have any active integrations.</p>
        </Card>
    </DefaultLayout>
</template>

<script>
import Card from '@/components/Card.vue';
import DefaultLayout from '@/components/layouts/DefaultLayout.vue';
import IconSet from '@/components/IconSet.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import Token from '@/stores/models/Token';
import TokenItem from '@/components/items/TokenItem.vue';

export default {
    components: {
        Card,
        DefaultLayout,
        IconSet,
        LoadingSpinner,
        TokenItem,
    },
    computed: {
        mcp_endpoint() {
            return window.location.origin + '/mcp/specifications';
        },
        tokens() {
            return Token.repository().collection.sortBy(token => token.name.toLocaleLowerCase());
        },
    },
    data() {
        return {
            is_loading: false,
        };
    },
    inject: ['api'],
    methods: {
        //
    },
    mounted() {
        if (this.tokens.isEmpty()) {
            this.is_loading = true;
        }

        this.api.get('tokens')
            .then((result) => {
                Token.repository().saveMany(result.data);
            })
            .finally(() => this.is_loading = false);
    },
};
</script>
