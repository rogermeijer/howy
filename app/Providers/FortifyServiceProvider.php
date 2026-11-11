<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Facades\Tenancy;
use App\Mail\Content\MailContent;
use App\Mail\Content\MailRenderer;
use App\Mail\SystemMails;
use App\Models\BetaAccessRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureMail();
        $this->configureRateLimiting();
    }

    /**
     * The password reset and verification mails, in Howy's frame and voice
     * instead of the framework's.
     */
    private function configureMail(): void
    {
        ResetPassword::toMailUsing(function (mixed $notifiable, string $token): MailMessage {
            $user = $notifiable instanceof User ? $notifiable : null;
            $email = (string) $user?->getEmailForPasswordReset();
            $broker = (string) config('auth.defaults.passwords');

            return $this->mailMessage(app(SystemMails::class)->resetPassword(
                $user?->name,
                $email,
                url(route('password.reset', ['token' => $token, 'email' => $email], false)),
                (int) config("auth.passwords.{$broker}.expire"),
            ));
        });

        VerifyEmail::toMailUsing(function (mixed $notifiable, string $url): MailMessage {
            $user = $notifiable instanceof User ? $notifiable : null;

            return $this->mailMessage(app(SystemMails::class)->verifyEmail($user?->name, (string) $user?->getEmailForVerification(), $url));
        });
    }

    private function mailMessage(MailContent $content): MailMessage
    {
        $rendered = app(MailRenderer::class)->render($content);

        return (new MailMessage)
            ->subject($rendered->subject)
            ->view(['html' => 'mail.rendered-html', 'text' => 'mail.rendered-text'], ['html' => $rendered->html, 'text' => $rendered->text]);
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        // Setup follows verification for whoever will be sent there, so the
        // page says what comes next.
        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
            'email' => $request->user()?->email,
            'setupAhead' => Tenancy::check()
                && ! Tenancy::account()->isSetUp()
                && $request->user()?->isAdminOf(Tenancy::account()) === true,
        ]));

        // Private beta: without a checked invite code the page asks for one first.
        Fortify::registerView(fn (Request $request) => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'code' => $this->rememberedInvite($request)?->code,
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    /**
     * The invite code from the code step, while it can still be redeemed.
     */
    private function rememberedInvite(Request $request): ?BetaAccessRequest
    {
        $code = $request->session()->get(BetaAccessRequest::SESSION_KEY);
        $invite = is_string($code) ? BetaAccessRequest::findRedeemable($code) : null;

        if ($invite === null) {
            $request->session()->forget(BetaAccessRequest::SESSION_KEY);
        }

        return $invite;
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
