import Filters from '../Filters';
import Model from '../Model';
import { useAuthStore } from '@/stores';

export default class Project extends Model {
    static repository_name = 'projects';

    constructor(data) {
        super(data);

        this.filters = new Filters(this.id);
    }

    onDelete() {
        for (const relation of ['features', 'actors', 'comments', 'collaborations', 'invitations']) {
            this.constructor.repository(relation).deleteMany(this[relation].pluck('id').all());
        }
    }

    get comments() {
        return this.constructor.repository('comments').collection.where('project_id', this.id);
    }
    set comments(comments) {
        this.constructor.repository('comments').saveMany(comments);
    }

    get collaborations() {
        return this.constructor.repository('collaborations').collection.where('project_id', this.id);
    }
    set collaborations(collaborations) {
        this.constructor.repository('collaborations').saveMany(collaborations);
    }

    get features() {
        return this.constructor.repository('features').collection.where('project_id', this.id);
    }
    set features(features) {
        this.constructor.repository('features').saveMany(features);
    }

    get invitations() {
        return this.constructor.repository('invitations').collection.where('project_id', this.id);
    }
    set invitations(invitations) {
        this.constructor.repository('invitations').saveMany(invitations);
    }

    get my_role() {
        const account = useAuthStore().account;

        return this.collaborations.firstWhere('account_id', account.id)?.role;
    }
    get my_role_name() {
        return {
            owner: 'Owner',
            editor: 'Editor',
            viewer: 'Viewer'
        }[this.my_role] ?? null;
    }

    get can_manage() {
        return this.my_role === 'owner' && !this.archived_at;
    }

    get can_restore() {
        return this.my_role === 'owner' && this.archived_at;
    }

    get can_comment() {
        return !this.archived_at;
    }

    get can_write() {
        return ['editor', 'owner'].includes(this.my_role) && !this.archived_at;
    }

    get actors() {
        return this.constructor.repository('actors').collection.where('project_id', this.id);
    }
    set actors(actors) {
        this.constructor.repository('actors').saveMany(actors);
    }
}
