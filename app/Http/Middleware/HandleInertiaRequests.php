<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\EmailInterpretation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Decoded catalogues, keyed by locale, so repeated shares within one request
     * do not re-read the file.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $translationCache = [];

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'account' => Tenancy::check()
                    ? ['id' => Tenancy::account()->id, 'name' => Tenancy::account()->name]
                    : null,
                'accounts' => $this->accountsFor($request->user()),
            ],
            // How many mails wait for someone to review what they would add:
            // the inbox's count in the top bar, for those who can act on it.
            'inbox' => fn (): array => [
                'needsReview' => Tenancy::check() && $request->user()?->isAdminOf(Tenancy::account())
                    ? EmailInterpretation::query()->where('needs_review', true)->count()
                    : 0,
            ],
            'locale' => app()->getLocale(),
            'locales' => array_map(
                fn (Locale $locale): array => ['value' => $locale->value, 'label' => $locale->label()],
                Locale::cases(),
            ),
            'timezone' => $request->attributes->getString('timezone', config()->string('app.timezone')),
            'translations' => $this->translations(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The active locale's string catalogue, for the frontend's t() helper.
     *
     * Read straight from the translator's loader so PHP and React share one source
     * of truth. English has no catalogue — the keys are the English strings — so
     * this is empty there and t() falls through to the key.
     *
     * @return array<string, string>
     */
    protected function translations(): array
    {
        $locale = app()->getLocale();

        return static::$translationCache[$locale] ??= Lang::getLoader()->load($locale, '*', '*');
    }

    /**
     * The accounts the user can switch between.
     *
     * Only the columns the switcher needs: sharing whole models would ship
     * timestamps for nothing and grow silently as the table does.
     *
     * @return list<array{id: int, name: string, is_admin: bool}>
     */
    protected function accountsFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values($user->accounts()
            ->select(['accounts.id', 'accounts.name'])
            ->orderBy('accounts.name')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'is_admin' => (bool) $account->getAttribute('pivot')->is_admin,
            ])
            ->all());
    }
}
