<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\LocaleUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Change the language the interface is rendered in.
     *
     * Open to guests, since the choice has to be available on the marketing and
     * auth screens before anyone has an account to store it against.
     */
    public function update(LocaleUpdateRequest $request): RedirectResponse
    {
        $locale = Locale::from($request->validated('locale'));

        // The cookie is the only place a guest's choice can live, and writing it
        // for signed-in users too means the two can never disagree — and the choice
        // survives logging out.
        Cookie::queue(Cookie::make(
            SetLocale::COOKIE,
            $locale->value,
            SetLocale::COOKIE_LIFETIME,
        ));

        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
