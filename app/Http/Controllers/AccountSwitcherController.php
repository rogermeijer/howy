<?php

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The accounts the top bar's switcher offers: a few of the user's other
 * accounts, searched here so that no page has to carry the whole list.
 */
class AccountSwitcherController extends Controller
{
    /** As many as the switcher shows; search reaches the rest. */
    public const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        /** @var User $user */
        $user = $request->user();
        $search = trim((string) ($validated['q'] ?? ''));

        // Backslash is Postgres' default LIKE escape, so a typed % or _ is
        // looked for literally instead of matching anything.
        $needle = addcslashes($search, '\\%_');

        $query = $this->others($user, $needle)->select(['accounts.id', 'accounts.name']);

        // Names starting with the search come first, then the rest by name.
        if ($needle !== '') {
            $query->orderByRaw('accounts.name ilike ? desc', ["{$needle}%"]);
        }

        $accounts = $query
            ->orderBy('accounts.name')
            ->orderBy('accounts.id')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'accounts' => $accounts
                ->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name])
                ->values()
                ->all(),
            'total' => $this->others($user, $needle)->count(),
        ]);
    }

    /**
     * The user's memberships other than the current account, matching the search.
     *
     * @return BelongsToMany<Account, User, AccountUser>
     */
    private function others(User $user, string $needle): BelongsToMany
    {
        $query = $user->accounts();

        if (Tenancy::check()) {
            $query->whereKeyNot(Tenancy::account()->id);
        }

        if ($needle !== '') {
            $query->whereLike('accounts.name', "%{$needle}%");
        }

        return $query;
    }
}
