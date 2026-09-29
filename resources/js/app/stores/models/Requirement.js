import Model from '../Model';
import { translate } from '@/translations';

export default class Requirement extends Model {
    static repository_name = 'requirements';

    get assignments() {
        return this.constructor.repository('assignments').collection.where('requirement_id', this.id);
    }
    set assignments(assignments) {
        this.constructor.repository('assignments').saveMany(assignments);
    }
    
    get comments() {
        return this.constructor.repository('comments').collection.where('commentable_type', 'requirement').where('commentable_id', this.id);
    }
    set comments(comments) {
        this.constructor.repository('comments').saveMany(comments);
    }

    get feature() {
        return this.constructor.repository('features').find(this.feature_id);
    }

    get has_unknowns() {
        return this.unknowns.isNotEmpty();
    }

    get has_tasks() {
        return this.tasks.isNotEmpty();
    }

    get is_complete() {
        if (this.completed_at === null || this.activity_at === null) {
            return false;
        }

        return Date.parse(this.completed_at) > Date.parse(this.activity_at);
    }

    get is_blocked() {
        return this.blocked_reason !== null;
    }

    get unknowns() {
        return this.constructor.repository('unknowns').collection.where('requirement_id', this.id);
    }
    set unknowns(unknowns) {
        this.constructor.repository('unknowns').saveMany(unknowns);
    }

    get short_title() {
        return this.title.charAt(0).toUpperCase() + this.title.slice(1);
    }

    get tasks() {
        return this.constructor.repository('tasks').collection.where('requirement_id', this.id);
    }
    set tasks(tasks) {
        this.constructor.repository('tasks').saveMany(tasks);
    }

    get title() {
        const locale = this.feature.project.locale ?? 'en';

        const actors = this.assignments.map(assignment => assignment.actor.name);

        if (actors.isEmpty()) {
            actors.push(translate('Users', locale));
        }

        const roles = new Intl.ListFormat(locale).format(actors.all());

        return roles + ' ' + translate('can', locale) + ' ' + this.name;
    }

    get is_filtered() {
        const filters = this.feature.project.filters;

        if (typeof filters.statuses.draft === 'boolean' && this.is_draft !== filters.statuses.draft) {
            return true;
        }

        if (typeof filters.statuses.completed === 'boolean' && this.is_complete !== filters.statuses.completed) {
            return true;
        }

        if (typeof filters.statuses.blocked === 'boolean' && this.is_blocked !== filters.statuses.blocked) {
            return true;
        }

        if (typeof filters.statuses.has_tasks === 'boolean' && this.tasks.isNotEmpty() !== filters.statuses.has_tasks) {
            return true;
        }

        if (typeof filters.statuses.has_unknowns === 'boolean' && this.unknowns.isNotEmpty() !== filters.statuses.has_unknowns) {
            return true;
        }

        if (filters.has_actors) {
            const actor_ids = this.assignments.pluck('actor_id');

            if (!Object.entries(filters.actors).every(([id, required]) => required ? actor_ids.contains(id) : actor_ids.doesntContain(id))) {
                return true;
            }
        }

        return false;
    }

    onDelete() {
        const comment_ids = this.comments.pluck('id').toArray();

        this.constructor.repository('comments').deleteMany(comment_ids);
    }
}
