<?php

namespace App\Http\Controllers\Auth;

use App\Auth\MagicLinkBroker;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MagicLinkController extends Controller
{
    private const string EMAIL_KEY = 'magic_link.email';

    public function __construct(private readonly MagicLinkBroker $magicLinks) {}

    /**
     * Email a link and a code to the person, if the address belongs to anyone.
     * The response is the same either way, so it reveals nobody.
     */
    public function store(Request $request): RedirectResponse
    {
        $email = Str::lower((string) $request->validate(['email' => ['required', 'email', 'max:255']])['email']);

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->magicLinks->forget($request->session());
        } else {
            $this->magicLinks->send($user, $request->session());
        }

        $request->session()->put(self::EMAIL_KEY, $email);

        return to_route('magic-link.code')->with('status', __('If this address belongs to someone in the cooperative, we have emailed them a link and a code to log in.'));
    }

    /**
     * Ask for the code from the email.
     */
    public function code(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get(self::EMAIL_KEY);

        if (! is_string($email)) {
            return to_route('login');
        }

        return Inertia::render('auth/MagicLinkCode', [
            'email' => $email,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Sign in the session that asked for the link, with the code it carries.
     */
    public function storeCode(Request $request): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string']])['code'];

        $user = $this->magicLinks->consumeCode($request->session(), $code);

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => __('The code is not valid or has expired.'),
            ]);
        }

        return $this->signIn($request, $user);
    }

    /**
     * Sign in the device that opened the link.
     */
    public function show(Request $request, string $token): RedirectResponse
    {
        $user = $this->magicLinks->consume($token);

        if ($user === null) {
            return to_route('login')->with('status', __('This link has expired or has already been used.'));
        }

        return $this->signIn($request, $user);
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);

        $request->session()->forget(self::EMAIL_KEY);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
