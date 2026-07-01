<?php

/**
 * User Controller
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\User\Http
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\User\Http;

use Lukaisu\Shared\Http\BaseController;
use Lukaisu\Shared\Infrastructure\Exception\AuthException;
use Lukaisu\Shared\Infrastructure\Globals;
use Lukaisu\Modules\User\Application\UserFacade;
use Lukaisu\Modules\User\Application\Services\AltchaService;
use Lukaisu\Modules\User\Infrastructure\AuthFormDataManager;
use Lukaisu\Shared\Infrastructure\Http\FlashMessageService;
use Lukaisu\Shared\Infrastructure\Http\ResponseInterface;
use Lukaisu\Shared\Infrastructure\Http\SecurityHeaders;

/**
 * Controller for user authentication operations.
 *
 * Handles login, registration, and logout functionality.
 */
class UserController extends BaseController
{
    /**
     * User facade instance.
     *
     * @var UserFacade
     */
    private UserFacade $userFacade;

    /**
     * Flash message service.
     *
     * @var FlashMessageService
     */
    private FlashMessageService $flash;

    /**
     * Auth form data manager.
     *
     * @var AuthFormDataManager
     */
    private AuthFormDataManager $formData;

    /**
     * ALTCHA proof-of-work captcha service.
     */
    private AltchaService $altcha;

    /**
     * Create a new UserController.
     *
     * @param UserFacade|null          $userFacade User facade (optional for BC)
     * @param FlashMessageService|null $flash      Flash message service
     * @param AuthFormDataManager|null $formData   Form data manager
     * @param AltchaService|null       $altcha     Captcha service (optional for BC)
     */
    public function __construct(
        ?UserFacade $userFacade = null,
        ?FlashMessageService $flash = null,
        ?AuthFormDataManager $formData = null,
        ?AltchaService $altcha = null
    ) {
        parent::__construct();
        $this->userFacade = $userFacade ?? $this->createDefaultFacade();
        $this->flash = $flash ?? new FlashMessageService();
        $this->formData = $formData ?? new AuthFormDataManager();
        $this->altcha = $altcha ?? AltchaService::fromEnvironment();
    }

    /**
     * Create a default UserFacade instance.
     *
     * @return UserFacade
     */
    private function createDefaultFacade(): UserFacade
    {
        $repository = new \Lukaisu\Modules\User\Infrastructure\MySqlUserRepository();
        return new UserFacade($repository);
    }

    /**
     * Display the login form.
     *
     * GET /login
     *
     * @return ResponseInterface|null
     */
    public function loginForm(): mixed
    {
        // If already authenticated, redirect to home
        if (Globals::isAuthenticated()) {
            return $this->redirect('/');
        }

        // Get flash error messages
        $errorMessages = $this->flash->getByTypeAndClear(FlashMessageService::TYPE_ERROR);
        $error = !empty($errorMessages) ? $errorMessages[0]['message'] : null;

        // Get persisted form data
        $username = $this->formData->getAndClearUsername();

        $this->render(__('user.login.page_title'), false);
        require __DIR__ . '/../Views/login.php';
        $this->endRender();

        return null;
    }

    // The packaged-client "choose server + log in" screen (GET /connect) is now
    // served by the bundled client: /connect 302s to the Svelte `ConnectPage`
    // island (index.html). The old `clientAuthForm()` action + its
    // `client_auth.php` Alpine view were retired. See the /app redirects in
    // routes.php.

    /**
     * Process the login form submission.
     *
     * POST /login
     *
     * @return ResponseInterface
     */
    public function login(): ResponseInterface
    {
        if (!$this->isPost()) {
            return $this->redirect('/login');
        }

        $usernameOrEmail = $this->post('username');
        $password = $this->post('password');
        $remember = $this->post('remember') === '1';

        // Basic validation
        if (empty($usernameOrEmail) || empty($password)) {
            $this->flash->error(__('user.flash.login_missing_credentials'));
            $this->formData->setUsername($usernameOrEmail);
            return $this->redirect('/login');
        }

        try {
            $user = $this->userFacade->login($usernameOrEmail, $password);

            // Set remember me cookie if requested
            if ($remember) {
                $this->setRememberCookie($user->id()->toInt());
            }

            // Drop any guest UI-language override so the account's own
            // app_language preference governs once signed in.
            if (isset($_COOKIE['lukaisu_lang']) && !headers_sent()) {
                setcookie('lukaisu_lang', '', ['expires' => time() - 3600, 'path' => '/']);
            }

            // Redirect to intended URL or home
            $redirectTo = $this->formData->getAndClearRedirectUrl('/');
            return $this->redirect($redirectTo);
        } catch (AuthException $e) {
            $this->flash->error($e->getMessage());
            $this->formData->setUsername($usernameOrEmail);
            return $this->redirect('/login');
        }
    }

    /**
     * Display the registration form.
     *
     * GET /register
     *
     * @return ResponseInterface|null
     */
    public function registerForm(): mixed
    {
        // GET /register now 302s into the bundled client (Svelte RegisterPage
        // island); the server-rendered register.php view was retired. This
        // method is retained only so
        // UserControllerTest::testClassHasRequiredPublicMethods keeps passing
        // (the orchestrator removes this coexistence cluster later) and is no
        // longer reached by routing — send any direct caller to the bundle.
        return $this->redirect('/register');
    }

    /**
     * Process the registration form submission.
     *
     * POST /register
     *
     * @return ResponseInterface
     */
    public function register(): ResponseInterface
    {
        if (!$this->isPost()) {
            return $this->redirect('/register');
        }

        // Check if registration is enabled
        if (!$this->isRegistrationEnabled()) {
            return $this->redirect('/login');
        }

        // Bot traps (cheap first line; the captcha is the real gate). A filled
        // honeypot or a near-instant submission is almost certainly a bot, so
        // pretend it worked — redirecting to /login wastes the bot's effort
        // without revealing the trap, and no account is created.
        if ($this->looksLikeBotSubmission()) {
            unset($_SESSION['register_form_time']);
            return $this->redirect('/login');
        }

        $username = $this->post('username');
        $email = $this->post('email');
        $password = $this->post('password');
        $passwordConfirm = $this->post('password_confirm');

        // Store form data for repopulation
        $this->formData->setUsername($username);
        $this->formData->setEmail($email);

        // Basic validation. Email is optional (the username is the unique
        // identity); it is only kept as a recovery/verification channel.
        if (empty($username) || empty($password)) {
            $this->flash->error(__('user.flash.register_missing_fields'));
            return $this->redirect('/register');
        }

        // Password confirmation
        if ($password !== $passwordConfirm) {
            $this->flash->error(__('user.flash.register_passwords_mismatch'));
            return $this->redirect('/register');
        }

        // Proof-of-work captcha: the browser must return a solved challenge.
        if (!$this->altcha->verify($this->post('altcha'))) {
            $this->flash->error(__('user.flash.register_captcha_failed'));
            return $this->redirect('/register');
        }

        try {
            $user = $this->userFacade->register($username, $email, $password);

            // Send verification email (non-blocking)
            $this->userFacade->sendVerificationEmail($user);

            // Note: setCurrentUser() only updates the in-process Globals; it
            // does NOT persist a session, so the next request would still see
            // an unauthenticated user and the auth middleware would bounce
            // them back to /login anyway. Surface the outcome by sending the
            // user straight to /login with a success message and prefilled
            // username — the login view renders $_SESSION['auth_success'].
            $this->formData->clearEmail();

            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            // Email-less account: issue a one-time recovery code (their only
            // way back into a forgotten account) and show it once.
            if ($user->email() === null) {
                $_SESSION['lukaisu_recovery_code'] = $this->userFacade->generateRecoveryCode($user);
                $_SESSION['lukaisu_recovery_context'] = 'register';
                return $this->redirect('/register/recovery-code');
            }

            $message = __('user.flash.register_success');
            if ($user->isAdmin()) {
                $message .= ' ' . __('user.flash.register_admin_granted');
            }
            if (!$user->isEmailVerified()) {
                $message .= ' ' . __('user.flash.register_verify_email');
            }
            $_SESSION['auth_success'] = $message;
            return $this->redirect('/login');
        } catch (\InvalidArgumentException $e) {
            $this->flash->error($e->getMessage());
            return $this->redirect('/register');
        } catch (\RuntimeException $e) {
            $this->flash->error(__('user.flash.register_failed'));
            return $this->redirect('/register');
        }
    }

    /** Minimum plausible seconds between serving and submitting the form. */
    private const MIN_REGISTER_SECONDS = 2;

    /**
     * Cheap, no-friction bot heuristics for the registration POST.
     *
     * - Honeypot: the form carries a `homepage` field hidden off-screen with
     *   tabindex=-1 and autocomplete=off; a human never fills it, naive bots do.
     * - Timing: a form submitted within {@see self::MIN_REGISTER_SECONDS} of
     *   being served was almost certainly auto-filled. Only enforced when a
     *   render timestamp exists, so a lost session can't false-positive.
     *
     * The real gate is the proof-of-work captcha; this just turns away the
     * cheapest bots for free.
     */
    private function looksLikeBotSubmission(): bool
    {
        if (trim($this->post('homepage')) !== '') {
            return true;
        }
        $formTime = (int) ($_SESSION['register_form_time'] ?? 0);
        return $formTime > 0 && (time() - $formTime) < self::MIN_REGISTER_SECONDS;
    }

    /**
     * Log out the current user.
     *
     * GET /logout
     *
     * @return ResponseInterface
     */
    public function logout(): ResponseInterface
    {
        // Invalidate and clear remember me cookie
        $currentUser = $this->userFacade->getCurrentUser();
        if ($currentUser !== null) {
            $this->userFacade->invalidateRememberToken($currentUser->id()->toInt());
        }
        $this->clearRememberCookie();

        // Logout via user facade
        $this->userFacade->logout();

        // Redirect to login
        return $this->redirect('/login');
    }

    // =========================================================================
    // Profile Methods
    // =========================================================================

    /**
     * Display the user profile form.
     *
     * GET /profile
     *
     * @return ResponseInterface|null
     */
    public function profileForm(): mixed
    {
        $user = $this->userFacade->getCurrentUser();
        if ($user === null) {
            if (Globals::isMultiUserEnabled()) {
                return $this->redirect('/login');
            }

            // Single-user mode: show simplified profile page
            $this->render(__('user.profile.page_title'), true);
            require __DIR__ . '/../Views/profile_single_user.php';
            $this->endRender();
            return null;
        }

        $errorMessages = $this->flash->getByTypeAndClear(FlashMessageService::TYPE_ERROR);
        $error = !empty($errorMessages) ? $errorMessages[0]['message'] : null;

        $successMessages = $this->flash->getByTypeAndClear(FlashMessageService::TYPE_SUCCESS);
        $success = !empty($successMessages) ? $successMessages[0]['message'] : null;

        $this->render(__('user.profile.page_title'), true);
        require __DIR__ . '/../Views/profile.php';
        $this->endRender();

        return null;
    }

    /**
     * Process profile update.
     *
     * POST /profile
     *
     * @return ResponseInterface
     */
    public function updateProfile(): ResponseInterface
    {
        if (!$this->isPost()) {
            return $this->redirect('/profile');
        }

        $user = $this->userFacade->getCurrentUser();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $username = $this->post('username');
        $email = $this->post('email');

        if (empty($username) || empty($email)) {
            $this->flash->error(__('user.flash.profile_missing_fields'));
            return $this->redirect('/profile');
        }

        try {
            $emailChanged = $this->userFacade->updateProfile($user, $username, $email);

            if ($emailChanged) {
                $this->userFacade->sendVerificationEmail($user);
                $this->flash->success(__('user.flash.profile_updated_verify'));
            } else {
                $this->flash->success(__('user.flash.profile_updated'));
            }
        } catch (\InvalidArgumentException $e) {
            $this->flash->error($e->getMessage());
        }

        return $this->redirect('/profile');
    }

    /**
     * Process password change.
     *
     * POST /profile/password
     *
     * @return ResponseInterface
     */
    public function changePassword(): ResponseInterface
    {
        if (!$this->isPost()) {
            return $this->redirect('/profile');
        }

        $user = $this->userFacade->getCurrentUser();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $currentPassword = $this->post('current_password');
        $newPassword = $this->post('new_password');
        $confirmPassword = $this->post('new_password_confirm');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->flash->error(__('user.flash.password_missing_fields'));
            return $this->redirect('/profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->flash->error(__('user.flash.password_mismatch'));
            return $this->redirect('/profile');
        }

        try {
            $this->userFacade->changePassword($user, $currentPassword, $newPassword);
            $this->flash->success(__('user.flash.password_changed'));
        } catch (\InvalidArgumentException $e) {
            $this->flash->error($e->getMessage());
        }

        return $this->redirect('/profile');
    }

    // Preferences are saved by the bundled client via POST /api/v1/settings
    // (Phase R); the native POST /profile/preferences form target
    // (savePreferences) was removed. The GET page 302s to the bundled settings.

    // =========================================================================
    // Email Verification Methods
    // =========================================================================

    /**
     * Verify a user's email via token link.
     *
     * GET /verify-email?token=...
     *
     * @return ResponseInterface
     */
    public function verifyEmail(): ResponseInterface
    {
        $token = $this->param('token');

        if (empty($token)) {
            $this->flash->error(__('user.flash.verify_invalid_link'));
            return $this->redirect('/');
        }

        $user = $this->userFacade->verifyEmail($token);

        if ($user === null) {
            $this->flash->error(__('user.flash.verify_expired'));
            return $this->redirect('/');
        }

        $this->flash->success(__('user.flash.verify_success'));
        return $this->redirect('/');
    }

    /**
     * Resend email verification link.
     *
     * POST /email/resend-verification
     *
     * @return ResponseInterface
     */
    public function resendVerification(): ResponseInterface
    {
        if (!$this->isPost()) {
            return $this->redirect('/');
        }

        $user = $this->userFacade->getCurrentUser();
        if ($user === null) {
            return $this->redirect('/login');
        }

        if ($user->isEmailVerified()) {
            $this->flash->success(__('user.flash.verify_already'));
            return $this->redirect('/');
        }

        $this->userFacade->sendVerificationEmail($user);
        $this->flash->success(__('user.flash.verify_sent'));
        return $this->redirect('/');
    }

    // =========================================================================
    // Password Reset Methods
    // =========================================================================

    /**
     * Display the forgot password form.
     *
     * GET /password/forgot
     *
     * @return void
     */
    public function forgotPasswordForm(): void
    {
        // GET /password/forgot now 302s into the bundled client (Svelte
        // ForgotPasswordPage island); the forgot_password.php view was retired.
        // Retained only so UserControllerTest::testClassHasRequiredPublicMethods
        // keeps passing; no longer reached by routing.
        $this->redirect('/password/forgot')->send();
    }

    /**
     * Process the forgot password form submission.
     *
     * POST /password/forgot
     *
     * @return void
     */
    public function forgotPassword(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/password/forgot');
        }

        $email = $this->post('email');

        if (empty($email)) {
            $this->flash->error(__('user.flash.forgot_missing_email'));
            $this->redirect('/password/forgot');
        }

        // Always show success message (prevents email enumeration)
        $this->userFacade->requestPasswordReset($email);

        $this->flash->success(__('user.flash.forgot_sent'));
        $this->redirect('/password/forgot');
    }

    /**
     * Display the reset password form.
     *
     * GET /password/reset?token=xxx
     *
     * @return void
     */
    public function resetPasswordForm(): void
    {
        // GET /password/reset now 302s into the bundled client (Svelte
        // ResetPasswordPage island); the reset_password.php view was retired.
        // Retained only so UserControllerTest::testClassHasRequiredPublicMethods
        // keeps passing; no longer reached by routing.
        $this->redirect('/password/reset')->send();
    }

    /**
     * Process the reset password form submission.
     *
     * POST /password/reset
     *
     * @return void
     */
    public function resetPassword(): void
    {
        if (!$this->isPost()) {
            $this->redirect('/password/forgot');
        }

        $token = $this->post('token');
        $password = $this->post('password');
        $passwordConfirm = $this->post('password_confirm');

        if (empty($token)) {
            $this->flash->error(__('user.flash.reset_token_invalid'));
            $this->redirect('/password/forgot');
        }

        if (empty($password)) {
            $this->flash->error(__('user.flash.reset_missing_password'));
            $this->redirect('/password/reset?token=' . urlencode($token));
        }

        if ($password !== $passwordConfirm) {
            $this->flash->error(__('user.flash.reset_passwords_mismatch'));
            $this->redirect('/password/reset?token=' . urlencode($token));
        }

        try {
            $success = $this->userFacade->completePasswordReset($token, $password);

            if ($success) {
                $this->flash->success(__('user.flash.reset_success'));
                $this->redirect('/login');
            } else {
                $this->flash->error(__('user.flash.reset_expired'));
                $this->redirect('/password/forgot');
            }
        } catch (\InvalidArgumentException $e) {
            $this->flash->error($e->getMessage());
            $this->redirect('/password/reset?token=' . urlencode($token));
        }
    }

    /**
     * Show the "reset with recovery code" form.
     *
     * GET /password/recover
     *
     * @return mixed Redirect response, or null after rendering.
     */
    public function recoverWithCodeForm(): mixed
    {
        // GET /password/recover now 302s into the bundled client (Svelte
        // RecoverPasswordPage island); the recover_password.php view was retired.
        // Retained for coexistence (removed later with the cluster); no longer
        // reached by routing.
        return $this->redirect('/password/recover');
    }

    /**
     * Process the "reset with recovery code" form.
     *
     * POST /password/recover
     *
     * @return ResponseInterface
     */
    public function recoverWithCode(): ResponseInterface
    {
        if (!$this->isPost()) {
            return $this->redirect('/password/recover');
        }

        $username = $this->post('username');
        $code = $this->post('recovery_code');
        $password = $this->post('password');
        $passwordConfirm = $this->post('password_confirm');

        $this->formData->setUsername($username);

        if (empty($username) || empty($code) || empty($password)) {
            $this->flash->error(__('user.flash.recover_missing_fields'));
            return $this->redirect('/password/recover');
        }

        if ($password !== $passwordConfirm) {
            $this->flash->error(__('user.flash.reset_passwords_mismatch'));
            return $this->redirect('/password/recover');
        }

        try {
            $newCode = $this->userFacade->resetPasswordWithRecoveryCode($username, $code, $password);

            // Reset succeeded: show the rotated code once, then on to login.
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['lukaisu_recovery_code'] = $newCode;
            $_SESSION['lukaisu_recovery_context'] = 'reset';
            return $this->redirect('/register/recovery-code');
        } catch (\InvalidArgumentException $e) {
            $this->flash->error($e->getMessage());
            return $this->redirect('/password/recover');
        }
    }

    /**
     * Try to restore session from remember-me cookie.
     *
     * This method is called during session bootstrap to check if
     * the user has a valid remember-me cookie and restore their session.
     *
     * @return bool True if session was restored, false otherwise
     */
    public function tryRestoreFromRememberCookie(): bool
    {
        // Check if already authenticated
        if (Globals::isAuthenticated()) {
            return true;
        }

        // Check for remember cookie
        $token = filter_input(INPUT_COOKIE, 'lukaisu_remember') ?? '';
        if (empty($token)) {
            return false;
        }

        // Validate token and get user
        $user = $this->userFacade->validateRememberToken($token);
        if ($user === null) {
            // Invalid/expired token - clear the cookie
            $this->clearRememberCookie();
            return false;
        }

        // Restore the session
        $this->userFacade->setCurrentUser($user);

        // Regenerate session ID for security
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Optionally refresh the token and cookie to extend the session
        $this->setRememberCookie($user->id()->toInt());

        return true;
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if user registration is enabled.
     *
     * @return bool
     */
    private function isRegistrationEnabled(): bool
    {
        return \Lukaisu\Shared\Infrastructure\Database\Settings::getWithDefault(
            'set-allow-registration'
        ) === '1';
    }

    /**
     * Set a "remember me" cookie with persistent token storage.
     *
     * The token is stored in the database and set as a cookie.
     * When the user returns, the token can be validated to restore the session.
     *
     * @param int $userId The user ID
     *
     * @return void
     */
    private function setRememberCookie(int $userId): void
    {
        $days = 30;
        $expires = time() + ($days * 24 * 60 * 60);

        // Generate and store token in database
        $token = $this->userFacade->setRememberToken($userId, $days);

        // Set cookie with secure flags
        setcookie(
            'lukaisu_remember',
            $token,
            [
                'expires' => $expires,
                'path' => '/',
                'secure' => SecurityHeaders::isSecureConnection(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Clear the "remember me" cookie.
     *
     * @return void
     */
    private function clearRememberCookie(): void
    {
        setcookie(
            'lukaisu_remember',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => SecurityHeaders::isSecureConnection(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
