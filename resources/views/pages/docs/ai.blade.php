<x-layout.docs title="AI-assisted Development">
    <article class="prose">
        <h1>AI-assisted Development</h1>

        <p>While Spectacular puts humans first, it's very good at steering AI coding agents. The specification is not just a planning document or project record anymore; it is structured context that agents can query while they work.</p>

        <h2 id="workflow">Recommended workflow</h2>

        <p>A specification is a far better interface for AI-assisted development than the traditional toilet-roll chat window. Here's how to do it:</p>

        <ol>
            <li>Create a new Spectacular specification as you would for a human. Identify the users, features and requirements so the agent has a context to work within.</li>
            <li>Where necessary, provide subtasks to help steer the implementation.</li>
            <li>Connect your coding agent to Spectacular through the MCP interface.</li>
            <li>Ask the agent to inspect the relevant specification. This is a good time to use the planning mode.</li>
            <li>Set it to work and then inspect the output.</li>
            <li>Update the specification where it has failed due to ambiguity and run the build again until satisfied.</li>
        </ol>

        <p>The agent should be smart enough to only request changes during the iterative process, saving on tokens.</p>

        <h2 id="prototyping">Rapid Prototyping</h2>

        <p>Even if you intend to implement the specification with human developers, the workflow above is an excellent way to discover holes in your specification. By mocking up the application in this way, you often find requirements you missed that may be costly to add later.</p>

        <h2 id="connect-agents">Connect agents with MCP</h2>

        <p>The Spectacular MCP server lets AI agents read and write your specifications directly. While Spectacular can just export the whole specification as Markdown, the MCP server makes the process transparent as the agent is able to read it directly. The agent is able to query for your most recent changes to the specification, maintaining the context while saving tokens.</p>

        <h2 id="server-url">Connecting</h2>

        <p>Anyone with a Spectacular account can use the MCP server to interrogate their specifications using the following URL.</p>

        <pre><code>https://spec.tacul.ar/mcp/specifications</code></pre>

        <p>We have agent-specific instructions for <a href="/docs/mcp/codex">Codex</a>, <a href="/docs/mcp/cursor">Cursor</a> and <a href="/docs/mcp/claude">Claude</a>.</p>

        <h2 id="authentication">Authentication</h2>

        <p>Most agents will attempt authentication using a standard OAuth flow like those of social logins. Simply follow the browser sign-in flow when your client asks you to authenticate.</p>

        <p>If your client does not support OAuth for remote MCP servers, simple bearer tokens are available in your account's <a href="/app/account/integrations">integrations</a> section. Pass the token as a bearer token in the HTTP authorization header:</p>

        <pre><code>Authorization: Bearer YOUR_SPECTACULAR_TOKEN</code></pre>

        <h2 id="available-tools">Available tools</h2>

        <p>The Spectacular MCP server lets agents inspect, author and organise specifications, track task progress, resolve unknowns and update requirement completion. Writing requires owner or editor access to an active project.</p>

        <p>Use <code>search_tools</code> to find individual entity operations and <code>execute_tools</code> to call them. Project listing, the full specification, changes and account listing remain directly available.</p>

        <table>
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>What it does</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>project-list</code></td>
                    <td>Lists the specifications available to the authenticated account.</td>
                </tr>
                <tr>
                    <td><code>project-get</code></td>
                    <td>Returns a full specification, including users, features, requirements, tasks, blockers, and unknowns.</td>
                </tr>
                <tr>
                    <td><code>project-get-details</code></td>
                    <td>Returns focused project context and metadata. Can include project revisions since a timestamp; use project-get for the complete specification.</td>
                </tr>
                <tr>
                    <td><code>actor-get</code><br><code>feature-get</code><br><code>requirement-get</code><br><code>task-get</code><br><code>assignment-get</code><br><code>unknown-get</code></td>
                    <td>Returns one entity of the named type. Each can include its revisions since a timestamp. These focused reads return description fields as stored HTML; write tools accept HTML.</td>
                </tr>
                <tr>
                    <td><code>project-get-changes</code></td>
                    <td>Returns project entities that changed since an ISO 8601 timestamp. Clients must discard cached descendants when their parent is deleted; individual child deletion records may be omitted.</td>
                </tr>
                <tr>
                    <td><code>project-create</code><br><code>project-edit</code></td>
                    <td>Creates or edits the specification’s product context, name and language. The creator becomes its owner. Project deletion is unavailable.</td>
                </tr>
                <tr>
                    <td><code>actor-create</code><br><code>actor-edit</code><br><code>actor-delete</code><br><code>actor-reorder</code></td>
                    <td>Manages product user roles and their display order. Deleting a role also soft-deletes its requirement assignments.</td>
                </tr>
                <tr>
                    <td><code>feature-create</code><br><code>feature-edit</code><br><code>feature-delete</code><br><code>feature-reorder</code></td>
                    <td>Manages capability groups and their display order. Removing a feature soft-deletes its specification descendants and retains feedback and history.</td>
                </tr>
                <tr>
                    <td><code>requirement-create</code><br><code>requirement-edit</code><br><code>requirement-delete</code><br><code>requirement-move</code><br><code>requirement-reorder</code></td>
                    <td>Manages user stories, moves them between features in the same specification and sets their display order. References are generated by the server and retained.</td>
                </tr>
                <tr>
                    <td><code>task-create</code><br><code>task-edit</code><br><code>task-delete</code><br><code>task-move</code><br><code>task-reorder</code></td>
                    <td>Manages implementation steps, task completion and display order. Tasks can move between requirements in the same specification.</td>
                </tr>
                <tr>
                    <td><code>unknown-create</code><br><code>unknown-edit</code><br><code>unknown-delete</code><br><code>unknown-move</code></td>
                    <td>Manages unresolved product questions. Use unknown-resolve to record an established answer before removing a question.</td>
                </tr>
                <tr>
                    <td><code>assignment-create</code><br><code>assignment-delete</code></td>
                    <td>Attaches or detaches product user roles from a requirement to generate its title. These are not assignments to project collaborators.</td>
                </tr>
                <tr>
                    <td><code>unknown-resolve</code></td>
                    <td>Appends an established clarification to the requirement description and removes the unknown in one operation.</td>
                </tr>
                <tr>
                    <td><code>requirement-set-completion</code></td>
                    <td>Marks an unblocked requirement as complete or reopens it.</td>
                </tr>
            </tbody>
        </table>

        <p>Focused read and write tools are separated by entity type, with no generic <code>type</code> selector. Each write tool accepts only that entity’s fields. Creation and organisation use explicit parent IDs: <code>project_id</code> for users and features, <code>feature_id</code> for requirements, and <code>requirement_id</code> for tasks, unknowns and assignments.</p>

        <h2 id="writing-specifications">Writing specifications</h2>

        <p>Ask your agent to read the current specification before changing it. Start with project purpose and scope, identify plural user roles such as <em>Managers</em> and <em>Waiters</em>, group capabilities into features such as <em>Orders</em>, and then write small requirements with clear acceptance criteria. Optional tasks describe implementation steps; unknowns record unanswered product questions.</p>

        <p>Requirement names contain only the action, such as <em>take orders</em>. Attach the <em>Waiters</em> role through an assignment to produce the title <em>Waiters can take orders</em>. Assignments refer to product users, not project collaborators.</p>

        <p>Provide description fields and clarifications as HTML for storage and rich text display. Clarifications supplied when resolving unknowns are appended to the existing description. User summaries are plain text. Editing an item changes only the supplied fields; omitted fields and child items remain in place. Supplying a description replaces its current content, so include any existing content you want to retain. Use the separate tools to add or remove tasks, unknowns and assignments. To clear a nullable field, supply <code>null</code>.</p>

        <p>Agents may author and revise draft requirements when you ask them to write a specification. Drafts still must not be implemented or marked complete as part of development work. Completing tasks does not complete the parent requirement. Changes to a completed requirement or its related items can make it incomplete again.</p>

        <p>Every edit appends a revision attributed to the authenticated account and marked as an AI change. Removals are soft deletions; prior revisions are retained. Comments and feedback cannot be created, changed or deleted through MCP. Account settings, collaborators, timestamps, permanent references and historical revisions are also outside the write interface. Projects cannot be deleted, and there are no purge, restore or rollback operations through MCP.</p>

        <p>Connected project views receive live updates when specification items are added, changed, moved, reordered or removed.</p>

        <h2 id="example-prompts">Example Prompts</h2>

        <p>The real advantage is not just giving an agent a one-off brief. Spectacular gives it the full specification: users, features, requirements, tasks, unresolved questions, blockers, assignments, comments, and recent changes. That lets the agent keep checking its work against the product you actually meant to build, especially later in development when the codebase and the plan have both started to move.</p>

        <pre><code>Can you list my Spectacular specifications?</code></pre>

        <pre><code>Write a specification for our restaurant ordering system. Start with the project context, Managers, Chefs and Waiters, then organise requirements under User, Orders, Stock and Menu. Record unanswered questions as unknowns.</code></pre>

        <pre><code>Add a draft requirement under Orders for splitting a bill. Assign it to Waiters, include the acceptance criteria we agreed, and record the unanswered question about service charges.</code></pre>

        <pre><code>Resolve the unknown about table numbers: each order must belong to exactly one table. Append this clarification to the requirement and retain its existing acceptance criteria.</code></pre>

        <pre><code>Read the Spectacular specification for this project before you propose an implementation plan. Identify the users, the relevant feature, and any requirements or unknowns that should shape the work.</code></pre>

        <pre><code>Before changing code, check Spectacular for blockers, unknowns, or recent specification changes that affect this feature. If anything is ambiguous, tell me what needs clarifying before you implement.</code></pre>

        <pre><code>Compare the current implementation with the Spectacular requirements for this feature. Point out any missing behaviours, edge cases, or assumptions that do not match the specification.</code></pre>

        <pre><code>Use Spectacular to find the requirements related to this bug. Explain which requirement is being violated, then make the smallest code change that brings the implementation back into line.</code></pre>

        <pre><code>Check what has changed in Spectacular since yesterday and tell me whether this branch needs to be updated before we continue.</code></pre>

        <pre><code>After implementing this feature, review the Spectacular tasks and requirements again. Confirm what is complete, what still needs testing, and whether any unknowns should be added back to the specification.</code></pre>
    </article>

    <x-docs.next-link href="/docs/projects">Projects</x-docs.next-link>
</x-layout.docs>
