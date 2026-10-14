import Model from '../Model';

export default class Actor extends Model {
    static repository_name = 'actors';

    onDelete() {
        const assignments = this.constructor.repository('assignments');
        assignments.deleteMany(assignments.collection.where('actor_id', this.id).pluck('id').all());
    }

    get project() {
        return this.constructor.repository('projects').find(this.project_id);
    }

    get requirements() {
        return this.constructor.repository('requirements').collection
            .filter(requirement => requirement.assignments.pluck('actor_id').contains(this.id));
    }
}
