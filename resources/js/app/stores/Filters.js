import { reactive, watch } from 'vue';

export default class Filters {
    exclude_features = false;
    hide_empty_features = false;
    features = [];
    statuses = {};
    actors = {};

    constructor(project_id) {
        const key = 'filters.' + project_id;

        try {
            const stored = JSON.parse(localStorage.getItem(key));

            // TODO Validate schema so dodgy localStorage values can't break the site.

            Object.assign(this, stored);
        } catch {
            localStorage.removeItem(key);
        }

        const filters = reactive(this);

        watch(filters, () => {
            if (
                this.features.length === 0
                && !this.hide_empty_features
                && Object.keys(this.statuses).length === 0
                && Object.keys(this.actors).length === 0
            ) {
                localStorage.removeItem(key);
                return;
            }

            const json = JSON.stringify(this);

            localStorage.setItem(key, json);
        }, { deep: true });

        return filters;
    }

    setFilter(type, key, value) {
        if (value === null) {
            delete this[type][key];
            return;
        }

        this[type][key] = value;
    }

    toggleFeature(key) {
        const index = this.features.indexOf(key);

        if (index === -1) {
            this.features.push(key);
        } else {
            this.features.splice(index, 1);

            if (this.features.length === 0) {
                this.exclude_features = false;
            }
        }
    }

    toggleFeatureMode() {
        this.exclude_features = !this.exclude_features;
    }

    clearFeatures() {
        this.features = [];
        this.exclude_features = false;
    }

    clearStatuses() {
        this.statuses = {};
    }

    clearActors() {
        this.actors = {};
    }

    clearAll() {
        this.clearFeatures();
        this.clearStatuses();
        this.clearActors();
        this.hide_empty_features = false;
    }

    get has_features() {
        return this.features.length > 0;
    }

    get has_statuses() {
        return Object.keys(this.statuses).length > 0;
    }

    get has_actors() {
        return Object.keys(this.actors).length > 0;
    }

    get has_filters() {
        return this.has_features || this.has_statuses || this.has_actors;
    }
}
