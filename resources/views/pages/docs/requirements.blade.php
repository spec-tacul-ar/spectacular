<x-layout.docs title="Requirements">
    <article class="prose">
        <h1>Requirements</h1>

        <p>Requirements are the core entities in Spectacular specifications. In other places, they might be called user stories. They describe the specific user needs or system interactions that contribute to the parent feature.</p>

        <p>How granular you are with your requirements is up to you but a general rule of thumb is that a developer should be able to implement a requirement within a day.</p>

        <h2 id="fields">Fields</h2>

        <h3>Title</h3>
        
        <p>This succinctly describes what can be achieved thanks to this requirement. Some like to add the motivation to the end of the user story name, but we recommend adding this to the description.</p>
        
        <p>You can specify which types of users are able to utilise the functionality defined in this requirement.</p>
        
        <p>For instance, you may want to make it clear that only administrators are allowed to ban users.</p>

        <x-docs.examples>
            <x-slot:good>
                <x-docs.example>Visitors can see the latest news items on the homepage.</x-docs.example>
                <x-docs.example>Staff can submit new sales reports.</x-docs.example>
                <x-docs.example>Managers can compile a roster of volunteers.</x-docs.example>
            </x-slot:good>

            <x-slot:bad>
                <x-docs.example>Users can news.</x-docs.example>
                <x-docs.example>Staff can submit new sales reports on the last Friday of the month if requested by management. This can be achieved through the dashboard.</x-docs.example>
                <x-docs.example>Supervisors, administrators, agents, team coordinators and team leads can compile a roster of volunteers.</x-docs.example>
            </x-slot:bad>
        </x-docs.examples>

        <h3>Description</h3>

        <p>Use this field to give more detail about this requirement and its acceptance criteria. It's a rich text field, so you can make it as detailed as needed.</p>

        <p>Try to include:</p>
        <ul>
            <li>Motivation for why the user might need this ability.</li>
            <li>Extra details about how the user interacts with this part of the system.</li>
            <li>Functionality you wish to exclude from the requirement. For instance, you might want to make it clear that the user login requirement does not include a <em>remember me</em> function.</li>
        </ul>

        {{-- TODO Much more advice here. --}}

        <h3>Source</h3>

        <p>Use this field to note where the requirement has come from. This is particularly useful when someone queries how something got into the specification. Be sure to keep adding to it as more information comes in.</p>

        <x-docs.examples>
            <x-slot:good>
                <x-docs.example>Project kickoff meeting</x-docs.example>
                <x-docs.example>Email from Jane, 14th Jan 2022</x-docs.example>
                <x-docs.example>Focus group notes ({{ now()->subMonths(2)->format('F Y') }})</x-docs.example>
            </x-slot:good>

            <x-slot:bad>
                <x-docs.example>Meeting</x-docs.example>
                <x-docs.example>Sandra's email</x-docs.example>
            </x-slot:bad>
        </x-docs.examples>

        <h3>Blocked</h3>

        <p>Check this if something is holding up the completion of the requirement and give a reason why.</p>

        <x-docs.examples>
            <x-slot:good>
                <x-docs.example>API keys provided by client do not work.</x-docs.example>
                <x-docs.example>Need legacy export format before proceeding.</x-docs.example>
                <x-docs.example>Need further clarification on questions below.</x-docs.example>
            </x-slot:good>

            <x-slot:bad>
                <x-docs.example>Not working</x-docs.example>
            </x-slot:bad>
        </x-docs.examples>

        <h3>Completion</h3>

        <p>Use the requirement's dropdown menu to mark it as complete or reopen it. A blocked requirement cannot be marked as complete. The menu hides this action for drafts, but API completion requests automatically remove draft status.</p>

        <p>Completion is independent of task progress. If the requirement or one of its related items changes after it was completed, the requirement is considered incomplete until it is marked complete again.</p>

        <h3 id="tasks">Tasks</h3>

        <p>Tasks are the real steps needed to complete the requirement. Tasks are optional because the implementation of a requirement can be trivial or self-evident.</p>

        <p>Remember that tasks can be reorganised and removed when editing requirements, should you need to.</p>

        <h4>Name</h4>

        <p>A succinct description of what needs to be done.</p>

        <x-docs.examples>
            <x-slot:good>
                <x-docs.example>Create a new database migration.</x-docs.example>
                <x-docs.example>Import existing user data into the new database.</x-docs.example>
                <x-docs.example>Point the legacy systems to the new database.</x-docs.example>
            </x-slot:good>

            <x-slot:bad>
                <x-docs.example>Make this work</x-docs.example>
                <x-docs.example>Fix this</x-docs.example>
            </x-slot:bad>
        </x-docs.examples>

        <h4>Is complete</h4>

        <p>Check this box if a task has been completed. Task completion helps track the work within a requirement, but does not automatically complete the requirement itself.</p>

        <h3 id="unknowns">Unknowns</h3>

        <p>This is a very powerful feature that lets you note talking points directly on the specification. Unlike blockers, Unknowns are for documenting ambiguities that should be resolved before the work can even commence.</p>

        <x-docs.examples>
            <x-slot:good>
                <x-docs.example>How long should it wait until a reminder notification is sent?</x-docs.example>
                <x-docs.example>What happens if the user is not authenticated for this action?</x-docs.example>
                <x-docs.example>Is there a limit to the number of seats a customer can reserve?</x-docs.example>
            </x-slot:good>

            <x-slot:bad>
                <x-docs.example>This doesn't make sense.</x-docs.example>
                <x-docs.example>We can't start until we have the legacy DB connection details.</x-docs.example>
            </x-slot:bad>
        </x-docs.examples>

        <h4 id="resolving-unknowns">Resolving unknowns</h4>

        <p>Each unknown can be quickly marked as resolved using its dropdown menu. This will show a modal where you can append the clarification to the requirement description.</p>

        <h4 id="drafts">Draft requirements</h4>

        <p>Mark a requirement as draft when it needs more detail or belongs to a later phase. Completed requirements cannot be marked as draft. Requirements can be both draft and blocked. Drafts are excluded from the project completion percentage, which includes all non-draft requirements regardless of their tasks.</p>

        <p>When AI agents read the specification through MCP, they should not work on draft requirements or their tasks.</p>

        <h2 id="deleting">Deleting requirements</h2>

        <p>Requirements can be deleted using the item in the requirement's dropdown menu.</p>
    </article>

    <x-docs.next-link href="/docs/team">Team</x-docs.next-link>
</x-layout.docs>
