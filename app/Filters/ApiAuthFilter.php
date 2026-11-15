<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\AccessTokens;
use CodeIgniter\Shield\Entities\User;

/**
 * Signs the caller of an API route in, by access token or by the admin session.
 *
 * A request with an `Authorization` header is checked as a token and never falls back to the
 * session. A request without one is signed in from the browser's cookies, which is how the
 * admin screens call the API. Any other request gets a 401, unless the route passes `optional`.
 *
 * Arguments, in any order:
 *  - A scope such as `entries.read`, which a token must carry. A session has no scopes and skips it.
 *  - `optional`, which lets a caller without credentials through as a visitor.
 *
 * A token also needs a user in one of the staff groups, as the token API has required from the start.
 * A token past its `expires` date is refused, and so is a login token that has no date at all.
 */
class ApiAuthFilter implements FilterInterface
{
    /**
     * Groups whose members may use the API with a token. These match the admin entries routes.
     */
    public const STAFF_GROUPS = ['superadmin', 'admin', 'developer'];

    /**
     * The name of the tokens that `api/v1/auth/login` hands out.
     */
    public const LOGIN_TOKEN = 'api-login';

    /**
     * How many days a login token works.
     */
    public const LOGIN_TOKEN_DAYS = 30;

    private static ?User $caller = null;

    /**
     * The user this filter signed in for the current request, or null for a visitor.
     *
     * Controllers read the caller here. Asking `auth()` about a visitor would start a session
     * or log a failed token attempt for every anonymous request.
     */
    public static function caller(): ?User
    {
        return self::$caller;
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        self::$caller = null;

        if (! $request instanceof IncomingRequest) {
            return;
        }

        helper(['cookie', 'setting']);

        $arguments ??= [];
        $optional  = in_array('optional', $arguments, true);
        $scopes    = array_values(array_diff($arguments, ['optional']));

        $header = $request->getHeaderLine(setting('Auth.authenticatorHeader')['tokens'] ?? 'Authorization');
        if ($header !== '') {
            return $this->signInByToken($header, $scopes);
        }

        if ($this->hasLoginCookie($request)) {
            $session = auth('session')->getAuthenticator();

            if ($session->loggedIn()) {
                $user = $session->getUser();

                if ($user->isBanned()) {
                    return $this->deny(ResponseInterface::HTTP_FORBIDDEN, $user->getBanMessage() ?? lang('Auth.logOutBannedUser'));
                }

                if (setting('Auth.recordActiveDate')) {
                    $session->recordActiveDate();
                }
                self::$caller = $user;

                return;
            }
        }

        if (! $optional) {
            return $this->deny(ResponseInterface::HTTP_UNAUTHORIZED, lang('Filter.loginRequired'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }

    /**
     * @param list<string> $scopes
     */
    private function signInByToken(string $header, array $scopes): ?ResponseInterface
    {
        /** @var AccessTokens $authenticator */
        $authenticator = auth('tokens')->getAuthenticator();

        if (! $authenticator->attempt(['token' => $header])->isOK()) {
            return $this->deny(ResponseInterface::HTTP_UNAUTHORIZED, lang('Auth.badToken'));
        }

        $user = $authenticator->getUser();

        // Shield stores an expiry date and never reads it, so the check is made here.
        // A login token from before the dates were added has none, and is treated as expired.
        $token   = $user->currentAccessToken();
        $expires = $token?->expires;
        if ($expires !== null ? ! Time::now()->isBefore($expires) : $token?->name === self::LOGIN_TOKEN) {
            $authenticator->logout();

            return $this->deny(ResponseInterface::HTTP_UNAUTHORIZED, lang('Filter.tokenExpired'));
        }

        if (! $user->isActivated()) {
            $authenticator->logout();

            return $this->deny(ResponseInterface::HTTP_FORBIDDEN, lang('Auth.activationBlocked'));
        }

        if ($user->isBanned()) {
            $authenticator->logout();

            return $this->deny(ResponseInterface::HTTP_FORBIDDEN, $user->getBanMessage() ?? lang('Auth.logOutBannedUser'));
        }

        foreach ($scopes as $scope) {
            if ($user->tokenCant($scope)) {
                return $this->deny(ResponseInterface::HTTP_FORBIDDEN, lang('Filter.apiNotAllowed'));
            }
        }

        if (! $user->inGroup(...self::STAFF_GROUPS)) {
            return $this->deny(ResponseInterface::HTTP_FORBIDDEN, lang('Filter.apiNotAllowed'));
        }

        if (setting('Auth.recordActiveDate')) {
            $authenticator->recordActiveDate();
        }
        self::$caller = $user;

        return null;
    }

    /**
     * Tell whether the browser sent a cookie that could carry a login. Checking first keeps a
     * request without one from starting a session.
     */
    private function hasLoginCookie(IncomingRequest $request): bool
    {
        $remember = setting('Auth.sessionConfig')['rememberCookieName'] ?? 'remember';

        return $request->getCookie(config('Session')->cookieName) !== null || get_cookie($remember) !== null;
    }

    private function deny(int $status, string $message): ResponseInterface
    {
        return service('response')->setStatusCode($status)->setJSON(['message' => $message]);
    }
}
