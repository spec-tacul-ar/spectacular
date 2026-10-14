<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Feature;
use App\Models\Requirement;
use App\Models\Scopes\WithoutHistoryScope;
use App\Policies\TokenPolicy;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WithoutHistoryScope::class);
    }

    public function boot(): void
    {
        Auth::provider('eloquent', function ($app, array $config) {
            return new class ($app['hash'], $config['model']) extends EloquentUserProvider {
                public function retrieveById($identifier)
                {
                    try {
                        return $this->createModel()->resolveRouteBinding($identifier);
                    } catch (ModelNotFoundException $exception) {
                        return null;
                    }
                }

                public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
                {
                    $account = $this->retrieveById($identifier);

                    if (! $account) {
                        return null;
                    }

                    $rememberToken = $account->getRememberToken();

                    return $rememberToken && hash_equals($rememberToken, $token) ? $account : null;
                }
            };
        });

        Blueprint::macro('revisionable', function (): void {
            $this->softDeletes();
            $this->uuid('uuid')->unique();
            $this->binary('history')->nullable();
        });

        Gate::policy(Token::class, TokenPolicy::class);

        Lang::addJsonPath(lang_path('spectacular'));

        Passport::authorizationView(function ($parameters) {
            return view('mcp.authorize', $parameters);
        });

        Passport::cookie('spectacular_token');

        Relation::enforceMorphMap([
            'account' => Account::class,
            'feature' => Feature::class,
            'requirement' => Requirement::class,
        ]);

        Request::macro('isMcp', fn() => app()->bound('mcp.request'));

        // Don't let the HTTP Host header dictate URLs.
        URL::useOrigin(config('app.url'));
    }
}
