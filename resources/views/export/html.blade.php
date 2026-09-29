<!doctype html>
<html lang="{{ $project->locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->name }}</title>
    <style>{!! Vite::content('resources/css/export.css') !!}</style>
</head>
<body>
    <main>
        <h1>{{ $project->name }}</h1>

        @if ($project->description)
            <div id="introduction">
                {!! clean($project->description) !!}
            </div>
        @endif

        @if ($project->actors->isNotEmpty())
            <div id="users">
                <h2>{{ __('Users', locale: $project->locale) }}</h2>

                @foreach ($project->actors as $actor)
                    <div class="user">
                        <h3>{{ $actor->name }}</h3>
                        <p>{!! nl2br(e($actor->summary)) !!}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($project->features->isNotEmpty())
            <div id="features">
                <h2>{{ __('Features', locale: $project->locale) }}</h2>

                @foreach ($project->features as $feature)
                    <div class="feature">
                        <h3>{{ $feature->name }}</h3>
                        {!! clean($feature->description) !!}

                        @if ($feature->requirements->isNotEmpty())
                            <div class="requirements">
                                @foreach ($feature->requirements as $requirement)
                                    <div @class(['requirement', 'requirement-draft' => $requirement->is_draft])>
                                        <h4>
                                            {{ $requirement->title }}
                                            @if ($requirement->is_draft)
                                                <span class="status status-draft">{{ __('Draft', locale: $project->locale) }}</span>
                                            @elseif ($requirement->is_blocked)
                                                <span class="status status-blocked">{{ __('Blocked', locale: $project->locale) }}</span>
                                            @elseif ($requirement->is_complete)
                                                <span class="status status-complete">{{ __('Complete', locale: $project->locale) }}</span>
                                            @endif
                                        </h4>

                                        @if ($requirement->is_blocked)
                                            <p class="blocker"><strong>{{ __('Blocked', locale: $project->locale) }}:</strong> {{ $requirement->blocked_reason }}</p>
                                        @endif

                                        @if ($requirement->description)
                                            <div class="description">
                                                {!! clean($requirement->description) !!}
                                            </div>
                                        @endif

                                        @if ($requirement->source)
                                            <p class="source"><strong>{{ __('Source', locale: $project->locale) }}:</strong> {{ $requirement->source }}</p>
                                        @endif

                                        @if ($requirement->unknowns->isNotEmpty())
                                            <div class="unknowns">
                                                <h5>{{ __('Unknowns', locale: $project->locale) }}</h5>

                                                <ul>
                                                    @foreach ($requirement->unknowns as $unknown)
                                                        <li>{{ $unknown->name }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if ($requirement->tasks->isNotEmpty())
                                            <div class="tasks">
                                                <h5>{{ __('Tasks', locale: $project->locale) }}</h5>

                                                <ul>
                                                    @foreach ($requirement->tasks as $task)
                                                        <li>
                                                            {{ $task->name }}
                                                            @if ($task->is_complete)
                                                                <span class="status status-complete">{{ __('Complete', locale: $project->locale) }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <p class="reference"><strong>{{ __('Ref', locale: $project->locale) }}:</strong> {{ $requirement->reference }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
