<p align="center"><a href="https://spec.tacul.ar" target="_blank"><img src="https://raw.githubusercontent.com/spec-tacul-ar/spectacular/main/public/images/logo.svg" width="400" alt="Spectacular Logo"></a></p>

<p align="center"><a href="https://raw.githubusercontent.com/spec-tacul-ar/spectacular/main/public/images/screenshot.png" target="_blank"><img src="https://raw.githubusercontent.com/spec-tacul-ar/spectacular/main/public/images/screenshot.png" alt="Screenshot"></a></p>

## About Spectacular

Spectacular is a free open-source specification workspace for AI-assisted software development.

Spectacular gives coding agents the structured product context they need before they touch the code. Instead of asking AI to infer intent from tickets, chats, and stale documents, Spectacular keeps the functional specification as a living source of truth that is clear for humans and directly available to AI tools.

The built-in MCP server lets agents such as Codex, Cursor, and Claude read and author your specifications, inspect project changes, and pull only the context they need while they work. Specification writes preserve revision history and cannot modify comments or feedback.

[LIVE DEMO](https://spec.tacul.ar)

### Features
* Maintain the specification as change requests come in
* Give AI agents structured requirements through MCP
* Track progress as requirements move through delivery
* Keep collaborators in sync with live project updates and editing indicators
* Document ambiguities and highlight blockers
* Effortlessly export to Markdown and HTML

### Roadmap
* More agent-facing MCP tools
* More export formats
* Dark mode for working late
* External documentation
* Browsable changelog (changes are already being stored!)
* Versioning, including a comparison tool
* Presence indicators and live updates
* Desktop app

## Installation

> [!TIP]
> Skip setup, updates, and backups with [Spectacular Cloud](https://spec.tacul.ar).

Spectacular is installed and configured like any other Laravel application.

```bash
git clone https://github.com/spec-tacul-ar/spectacular.git
cd spectacular
composer setup
php artisan serve
```

To upgrade, simply grab the latest code and run `composer setup` again.

### Migrating to v2

If you previously used Spectacular as a solo user, create or register the account that should own your existing projects, then assign any legacy solo projects to it:

```bash
php artisan spectacular:solo:migrate your-email@example.com
```

The solo migration command only claims projects that do not already have collaborators, so shared projects are left untouched.

### Docker

If you don't have PHP available, you can run the app with Docker. Once ready, you'll find Spectacular running at [http://localhost:8000](http://localhost:8000).

```bash
docker compose up --build
```

Docker provides the PHP, Composer, and Node runtime. Your checkout is mounted into the container, so the database and uploaded files stay on your machine.

Upgrades are simple too - just grab the latest code and restart Docker. Rebuild the container when the runtime dependencies change.

### Real-time collaboration

Spectacular can keep a project in sync for everyone viewing it and show when another collaborator is editing a project, actor, feature, or requirement. Real-time collaboration is disabled by default, so self-hosted installations continue to work without a WebSocket service.

To enable it, configure a Laravel broadcasting driver and a queue worker for your deployment. See the [Laravel broadcasting documentation](https://laravel.com/docs/13.x/broadcasting) for supported drivers and configuration instructions.

## MCP for AI Agents

Connect AI coding agents to Spectacular's MCP server so they can read the latest specification while they work and author changes when you ask. Owners and editors can create, edit, move, reorder and soft-delete specification items, resolve unknowns and track completion. Comments, feedback and historical revisions are not writable through MCP.

Cloud endpoint:

```text
https://spec.tacul.ar/mcp/specifications
```

Self-hosted endpoint:

```text
https://your-spectacular.example.com/mcp/specifications
```

The MCP server supports OAuth where your agent supports it. You can also create a bearer token in Account integrations and pass it as an `Authorization: Bearer ...` header.

See the [AI-assisted development docs](https://spec.tacul.ar/docs/ai) for Codex, Cursor, Claude, and generic MCP setup examples.

## Contributing

Thank you for considering contributing to Spectacular!

Spectacular has been designed with simplicity in mind so you can customise it for your own needs. If you've made something you think everyone could benefit from, please consider sharing it back.

See [CONTRIBUTING.md](https://github.com/spec-tacul-ar/spectacular/blob/main/CONTRIBUTING.md) for more information.

## Security

If you discover a security vulnerability within Spectacular, please send an e-mail to Matthew White via [matt@matthewwhite.me.uk](mailto:matt@matthewwhite.me.uk). All security vulnerabilities will be promptly addressed.

## License

Spectacular - an open-source functional specification manager\
Copyright (C) 2026 Spectacular Software Limited

This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with this program.  If not, see <https://www.gnu.org/licenses/>.
