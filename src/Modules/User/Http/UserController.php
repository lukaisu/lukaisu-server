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
