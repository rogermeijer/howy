<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Users across the platform. The super-admin flag is written here and nowhere
 * else, with forceFill, and read only through isSuperAdmin().
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->with(['accounts:id,name', 'betaAccessRequest:id,user_id,code'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->isSuperAdmin(),
                'email_verified' => $user->email_verified_at !== null,
                'accounts' => $this->memberships($user),
                'beta_code' => $user->betaAccessRequest?->code,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/create', [
            'accounts' => $this->accountOptions(),
            'locales' => Options::locales(),
            'accountId' => $request->integer('account') ?: null,
        ]);
    }

    /**
     * Create a user who chooses their own password: they get the same reset
     * link as "forgot password". The address is trusted, so it counts as
     * verified.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = new User([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => Str::password(32),
                'locale' => $request->input('locale') ?: null,
            ]);
            $user->forceFill([
                'is_admin' => $request->boolean('is_super_admin'),
                'email_verified_at' => now(),
            ])->save();

            if ($request->filled('account_id')) {
                $account = Account::query()->findOrFail($request->integer('account_id'));
                $account->users()->attach($user, ['is_admin' => $request->boolean('account_admin')]);
                $user->forceFill(['active_account_id' => $account->id])->save();
            }

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created. They got an email to choose a password.')]);

        return to_route('admin.users.show', $user);
    }

    public function show(User $user): Response
    {
        $user->load(['accounts', 'betaAccessRequest']);

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale?->value,
                'is_super_admin' => $user->isSuperAdmin(),
                'email_verified' => $user->email_verified_at !== null,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'beta_code' => $user->betaAccessRequest?->code,
                'created_at' => $user->created_at?->toIso8601String(),
                'is_self' => $user->is(request()->user()),
            ],
            'memberships' => $this->memberships($user),
            'accounts' => $this->accountOptions(),
            'locales' => Options::locales(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $superAdmin = $request->boolean('is_super_admin');

        if ($user->is($request->user()) && ! $superAdmin) {
            return back()->withErrors(['is_super_admin' => __('You cannot remove your own super-admin rights.')]);
        }

        $user->fill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'locale' => $request->input('locale') ?: null,
        ]);
        $user->forceFill(['is_admin' => $superAdmin])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Delete a user. The accounts they belong to stay, with their data.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => __('You cannot delete yourself here. Use your profile settings.')]);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * @return list<array{id: int, name: string, is_admin: bool}>
     */
    private function memberships(User $user): array
    {
        return array_values($user->accounts
            ->sortBy('name')
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'is_admin' => (bool) $account->getAttribute('pivot')->is_admin,
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function accountOptions(): array
    {
        return array_values(Account::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Account $account): array => ['id' => $account->id, 'name' => $account->name])
            ->all());
    }
}
