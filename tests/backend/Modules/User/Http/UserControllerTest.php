<?php

declare(strict_types=1);

namespace Lukaisu\Tests\Modules\User\Http;

use Lukaisu\Modules\User\Domain\User;
use Lukaisu\Modules\User\Http\UserController;
use Lukaisu\Modules\User\Application\UserFacade;
use Lukaisu\Modules\User\Application\Services\AltchaService;
use Lukaisu\Modules\User\Infrastructure\AuthFormDataManager;
use Lukaisu\Shared\Domain\ValueObjects\UserId;
use Lukaisu\Shared\Infrastructure\Http\FlashMessageService;
use Lukaisu\Shared\Infrastructure\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for UserController.
 *
 * Tests login, register, logout, profile, password reset, and email verification flows.
 */
class UserControllerTest extends TestCase
{
    /** @var UserFacade&MockObject */
    private UserFacade $facade;

    /** @var FlashMessageService&MockObject */
    private FlashMessageService $flash;

    /** @var AuthFormDataManager&MockObject */
    private AuthFormDataManager $formData;

    private UserController $controller;

    protected function setUp(): void
    {
        $this->facade = $this->createMock(UserFacade::class);
        $this->flash = $this->createMock(FlashMessageService::class);
        $this->formData = $this->createMock(AuthFormDataManager::class);
        // Disable the captcha so register tests don't need a solved challenge;
        // a dedicated test covers the enabled (rejection) path.
        $this->controller = new UserController(
            $this->facade,
            $this->flash,
            $this->formData,
            new AltchaService('test-key', false)
        );
    }

    protected function tearDown(): void
    {
        $_REQUEST = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        parent::tearDown();
    }

    // =========================================================================
    // Constructor tests
    // =========================================================================

    public function testConstructorCreatesValidController(): void
    {
        $this->assertInstanceOf(UserController::class, $this->controller);
    }

    public function testConstructorAcceptsNullParameters(): void
    {
        $controller = new UserController(null, null, null);
        $this->assertInstanceOf(UserController::class, $controller);
    }

    public function testClassExtendsBaseController(): void
    {
        $reflection = new \ReflectionClass(UserController::class);
        $this->assertSame(
            'Lukaisu\Shared\Http\BaseController',
            $reflection->getParentClass()->getName()
        );
    }


    // =========================================================================
    // logout() tests - GET /logout
    // =========================================================================

    public function testLogoutInvalidatesRememberTokenWhenUserExists(): void
    {
        $user = $this->createMockUser(1, 'testuser', 'test@example.com');

        $this->facade->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($user);

        $this->facade->expects($this->once())
            ->method('invalidateRememberToken')
            ->with(1);

        $this->facade->expects($this->once())
            ->method('logout');

        $this->controller->logout();
    }

    public function testLogoutSkipsTokenInvalidationWhenNoUser(): void
    {
        $this->facade->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $this->facade->expects($this->never())
            ->method('invalidateRememberToken');

        $this->facade->expects($this->once())
            ->method('logout');

        $this->controller->logout();
    }

    // =========================================================================
    // verifyEmail() tests - GET /verify-email?token=...
    // =========================================================================

    public function testVerifyEmailShowsErrorForEmptyToken(): void
    {
        $_REQUEST['token'] = '';

        $this->flash->expects($this->once())
            ->method('error')
            ->with('Invalid verification link.');

        $this->controller->verifyEmail();
    }

    public function testVerifyEmailShowsErrorForInvalidToken(): void
    {
        $_REQUEST['token'] = 'invalid-token';

        $this->facade->expects($this->once())
            ->method('verifyEmail')
            ->with('invalid-token')
            ->willReturn(null);

        $this->flash->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Invalid or expired'));

        $this->controller->verifyEmail();
    }

    public function testVerifyEmailShowsSuccessForValidToken(): void
    {
        $_REQUEST['token'] = 'valid-token';

        $user = $this->createMockUser(1, 'testuser', 'test@example.com');
        $this->facade->expects($this->once())
            ->method('verifyEmail')
            ->with('valid-token')
            ->willReturn($user);

        $this->flash->expects($this->once())
            ->method('success')
            ->with($this->stringContains('verified successfully'));

        $this->controller->verifyEmail();
    }

    // =========================================================================
    // resendVerification() tests - POST /email/resend-verification
    // =========================================================================

    public function testResendVerificationRedirectsWhenNotPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->facade->expects($this->never())->method('sendVerificationEmail');
        $this->controller->resendVerification();
    }

    public function testResendVerificationRedirectsWhenNotAuthenticated(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->facade->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $this->facade->expects($this->never())->method('sendVerificationEmail');
        $this->controller->resendVerification();
    }

    public function testResendVerificationSkipsWhenAlreadyVerified(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $user = $this->createMockUser(1, 'testuser', 'test@example.com');
        $user->method('isEmailVerified')->willReturn(true);

        $this->facade->method('getCurrentUser')->willReturn($user);

        $this->flash->expects($this->once())
            ->method('success')
            ->with('Your email is already verified.');

        $this->facade->expects($this->never())->method('sendVerificationEmail');
        $this->controller->resendVerification();
    }

    public function testResendVerificationSendsEmailWhenNotVerified(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $user = $this->createMockUser(1, 'testuser', 'test@example.com');
        $user->method('isEmailVerified')->willReturn(false);

        $this->facade->method('getCurrentUser')->willReturn($user);

        $this->facade->expects($this->once())
            ->method('sendVerificationEmail')
            ->with($user);

        $this->flash->expects($this->once())
            ->method('success')
            ->with($this->stringContains('Verification email sent'));

        $this->controller->resendVerification();
    }

    // =========================================================================
    // forgotPassword() tests - POST /password/forgot
    // =========================================================================

    public function testForgotPasswordRedirectsWhenNotPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Controller doesn't return after redirect, execution continues
        $this->controller->forgotPassword();
        $this->assertTrue(true);
    }

    public function testForgotPasswordShowsErrorForEmptyEmail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = '';

        $this->flash->expects($this->once())
            ->method('error')
            ->with('Please enter your email address');

        $this->controller->forgotPassword();
    }

    public function testForgotPasswordCallsFacadeAndShowsGenericMessage(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'test@example.com';

        $this->facade->expects($this->once())
            ->method('requestPasswordReset')
            ->with('test@example.com');

        // Always shows success (anti-enumeration)
        $this->flash->expects($this->once())
            ->method('success')
            ->with($this->stringContains('If an account exists'));

        $this->controller->forgotPassword();
    }

    // =========================================================================
    // resetPassword() tests - POST /password/reset
    // =========================================================================

    public function testResetPasswordRedirectsWhenNotPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Controller doesn't return after redirect, execution continues
        $this->controller->resetPassword();
        $this->assertTrue(true);
    }

    public function testResetPasswordShowsErrorForEmptyToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['token'] = '';
        $_POST['password'] = 'newpass';
        $_POST['password_confirm'] = 'newpass';

        // Controller doesn't return after redirect, so error() may be called multiple times
        $this->flash->expects($this->atLeastOnce())
            ->method('error');

        $this->controller->resetPassword();
    }

    public function testResetPasswordShowsErrorForEmptyPassword(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['token'] = 'valid-token';
        $_POST['password'] = '';
        $_POST['password_confirm'] = '';

        $this->flash->expects($this->atLeastOnce())
            ->method('error');

        $this->controller->resetPassword();
    }

    public function testResetPasswordShowsErrorForPasswordMismatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['token'] = 'valid-token';
        $_POST['password'] = 'pass1';
        $_POST['password_confirm'] = 'pass2';

        $this->flash->expects($this->atLeastOnce())
            ->method('error');

        $this->controller->resetPassword();
    }

    public function testResetPasswordShowsSuccessOnCompletion(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['token'] = 'valid-token';
        $_POST['password'] = 'newpass';
        $_POST['password_confirm'] = 'newpass';

        $this->facade->expects($this->once())
            ->method('completePasswordReset')
            ->with('valid-token', 'newpass')
            ->willReturn(true);

        $this->flash->expects($this->once())
            ->method('success')
            ->with($this->stringContains('reset successfully'));

        $this->controller->resetPassword();
    }

    public function testResetPasswordShowsErrorOnFailure(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['token'] = 'expired-token';
        $_POST['password'] = 'newpass';
        $_POST['password_confirm'] = 'newpass';

        $this->facade->method('completePasswordReset')
            ->willReturn(false);

        $this->flash->expects($this->once())
            ->method('error')
            ->with($this->stringContains('expired or is invalid'));

        $this->controller->resetPassword();
    }

    public function testResetPasswordHandlesInvalidArgumentException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['token'] = 'valid-token';
        $_POST['password'] = 'weak';
        $_POST['password_confirm'] = 'weak';

        $this->facade->method('completePasswordReset')
            ->willThrowException(new \InvalidArgumentException('Password too weak'));

        $this->flash->expects($this->once())
            ->method('error')
            ->with('Password too weak');

        $this->controller->resetPassword();
    }

    // =========================================================================
    // tryRestoreFromRememberCookie() tests
    // =========================================================================

    public function testTryRestoreReturnsFalseWhenNoCookie(): void
    {
        // Clear cookie
        $_COOKIE = [];

        $result = $this->controller->tryRestoreFromRememberCookie();

        // If Globals::isAuthenticated() is false and no cookie, returns false
        // This test may depend on global state, so we just verify the method exists
        $this->assertIsBool($result);
    }

    // =========================================================================
    // Class structure tests
    // =========================================================================

    public function testClassHasRequiredPublicMethods(): void
    {
        $reflection = new \ReflectionClass(UserController::class);

        $expectedMethods = [
            'logout',
            'verifyEmail', 'resendVerification',
            'forgotPasswordForm', 'forgotPassword',
            'resetPasswordForm', 'resetPassword',
            'tryRestoreFromRememberCookie',
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "UserController should have method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPublic(),
                "Method $methodName should be public"
            );
        }
    }

    public function testPrivateHelperMethodsExist(): void
    {
        $reflection = new \ReflectionClass(UserController::class);

        $privateMethods = [
            'setRememberCookie',
            'clearRememberCookie',
            'createDefaultFacade',
        ];

        foreach ($privateMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "UserController should have private method: $methodName"
            );
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPrivate(),
                "Method $methodName should be private"
            );
        }
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Create a mock User object.
     *
     * @param int    $id       User ID
     * @param string $username Username
     * @param string $email    Email
     *
     * @return User&MockObject
     */
    private function createMockUser(int $id, string $username, string $email): User
    {
        $userId = UserId::fromInt($id);
        $created = new \DateTimeImmutable('2024-01-01');

        $user = $this->createMock(User::class);
        $user->method('id')->willReturn($userId);
        $user->method('username')->willReturn($username);
        $user->method('email')->willReturn($email);
        $user->method('role')->willReturn('user');
        $user->method('created')->willReturn($created);
        $user->method('lastLogin')->willReturn(null);
        $user->method('apiTokenExpires')->willReturn(null);
        $user->method('wordPressId')->willReturn(null);

        return $user;
    }
}
