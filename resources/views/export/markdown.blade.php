# {{ Str::escapeMarkdown($project->name) }}

@if ($project->description)
{{----}}{{ Str::htmlToMarkdown($project->description) }}
@endif

@if ($project->actors->isNotEmpty())
{{----}}## {{ Str::escapeMarkdown(__('Users', locale: $project->locale)) }}

{{----}}@foreach ($project->actors as $actor)
{{----}}{{----}}### {{ Str::escapeMarkdown($actor->name) }}
{{----}}{{----}}{{ $actor->summary ? Str::escapeMarkdown($actor->summary) . "\n" : '' }}
{{----}}@endforeach
@endif
@if ($project->features->isNotEmpty())
{{----}}## {{ Str::escapeMarkdown(__('Features', locale: $project->locale)) }}

{{----}}@foreach ($project->features as $feature)
{{----}}{{----}}### {{ Str::escapeMarkdown($feature->name) }}
{{----}}{{----}}
{{----}}{{----}}{{ $feature->description ? Str::htmlToMarkdown($feature->description) . "\n" : '' }}
{{----}}{{----}}@foreach ($feature->requirements as $requirement)
{{----}}{{----}}{{----}}#### ({{ $requirement->reference }}) {{ Str::escapeMarkdown($requirement->title) }}{{ $requirement->is_draft ? ' [' . __('Draft', locale: $project->locale) . ']' : ($requirement->is_complete && !$requirement->is_blocked ? ' [' . __('Complete', locale: $project->locale) . ']' : null) }}
{{----}}{{----}}{{----}}{{ $requirement->is_blocked ? "\n" . '**[' . __('Blocked', locale: $project->locale) . '] ' . Str::escapeMarkdown($requirement->blocked_reason) . '**' . "\n" : '' }}
{{----}}{{----}}{{----}}{{ $requirement->description ? Str::htmlToMarkdown($requirement->description) . "\n" : '' }}
{{----}}{{----}}{{----}}{{ $requirement->source ? '*' . __('Source', locale: $project->locale) . ': ' . Str::escapeMarkdown($requirement->source) . '*' . "\n" : '' }}
{{----}}{{----}}{{----}}@if ($requirement->tasks->isNotEmpty())
{{----}}{{----}}{{----}}{{----}}##### {{ Str::escapeMarkdown(__('Tasks', locale: $project->locale)) }}
{{----}}{{----}}{{----}}{{----}}@foreach ($requirement->tasks as $task)
{{----}}{{----}}{{----}}{{----}}{{----}}* {{ Str::escapeMarkdown($task->name) }}{{ $task->is_complete ? ' [' . __('Complete', locale: $project->locale) . ']' : null }}
{{----}}{{----}}{{----}}{{----}}@endforeach
{{----}}{{----}}{{----}}{{----}}
{{----}}{{----}}{{----}}@endif
{{----}}{{----}}{{----}}@if ($requirement->unknowns->isNotEmpty())
{{----}}{{----}}{{----}}{{----}}##### {{ Str::escapeMarkdown(__('Unknowns', locale: $project->locale)) }}
{{----}}{{----}}{{----}}{{----}}@foreach ($requirement->unknowns as $unknown)
{{----}}{{----}}{{----}}{{----}}{{----}}* {{ Str::escapeMarkdown($unknown->name) }}
{{----}}{{----}}{{----}}{{----}}@endforeach
{{----}}{{----}}{{----}}{{----}}
{{----}}{{----}}{{----}}@endif
{{----}}{{----}}@endforeach
{{----}}@endforeach
@endif
