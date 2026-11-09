<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Enums\LoginOutcome;
use App\Services\AuthService;
use App\Services\PasswordHasher;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArrayRateLimiter;
use Tests\Support\ArraySession;
use Tests\Support\InMemoryAdminUsers;
use Tests\Support\RecordingAudit;

final class AuthServiceTest extends TestCase
{
    private const PASSWORD = 'correct horse battery';

    private InMemoryAdminUsers $users;
    private ArraySession $session;
    private Csrf $csrf;
    private ArrayRateLimiter $limiter;
    private RecordingAudit $audit;
    private PasswordHasher $hasher;
    private AuthService $auth;
    private int $adminId;

    protected function setUp(): void
    {
        $this->users = new InMemoryAdminUsers();
        $this->session = new ArraySession();
        $this->csrf = new Csrf($this->session);
        $this->limiter = new ArrayRateLimiter();
        $this->audit = new RecordingAudit();
        $this->hasher = new PasswordHasher();
        $this->auth = $this->newService();
        $this->adminId = $this->users->create('admin', $this->hasher->hash(self::PASSWORD), false);
    }

    private function newService(): AuthService
    {
        return new AuthService($this->users, $this->session, $this->csrf, $this->limiter, $this->audit, $this->hasher, 5, 15);
    }

    public function testSuccessfulLoginRegeneratesSessionAndRotatesCsrf(): void
    {
        $before = $this->csrf->token();

        $this->assertSame(LoginOutcome::Success, $this->auth->attempt('admin', self::PASSWORD, '1.1.1.1'));

        $this->assertSame(1, $this->session->regenerations);
        $this->assertNotSame($before, $this->csrf->token());
        $this->assertSame($this->adminId, $this->auth->currentAdmin()['id'] ?? null);
        $this->assertContains('auth.login', $this->audit->actions());
    }

    public function testUsernameLookupIsCaseInsensitiveAndTrimmed(): void
    {
        $this->assertSame(LoginOutcome::Success, $this->auth->attempt('  ADMIN ', self::PASSWORD, '1.1.1.1'));
    }

    public function testWrongPasswordFailsAndCountsAttempt(): void
    {
        $this->assertSame(LoginOutcome::Invalid, $this->auth->attempt('admin', 'wrong password!!', '1.1.1.1'));

        $this->assertSame(1, $this->users->rows[$this->adminId]['failed_attempts']);
        $this->assertNull($this->auth->currentAdmin());
        $this->assertContains('auth.login_failed', $this->audit->actions());
    }

    public function testUnknownUserGivesSameOutcomeAndNeverLogsRawUsername(): void
    {
        $outcome = $this->auth->attempt('ghost-user', 'whatever password', '1.1.1.1');

        $this->assertSame(LoginOutcome::Invalid, $outcome);
        $this->assertStringNotContainsString('ghost-user', (string) json_encode($this->audit->entries));
    }

    public function testAccountLocksAfterMaxFailuresEvenWithCorrectPassword(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->auth->attempt('admin', 'wrong password!!', '1.1.1.1');
        }

        $this->assertSame(LoginOutcome::Locked, $this->auth->attempt('admin', self::PASSWORD, '1.1.1.1'));
        $this->assertNull($this->auth->currentAdmin());
        $this->assertContains('auth.login_blocked', $this->audit->actions());
    }

    public function testExpiredLockAllowsLoginAndSuccessResetsCounters(): void
    {
        $this->users->rows[$this->adminId]['failed_attempts'] = 5;
        $this->users->rows[$this->adminId]['locked_until'] = gmdate('Y-m-d H:i:s', time() - 60);

        $this->assertSame(LoginOutcome::Success, $this->auth->attempt('admin', self::PASSWORD, '1.1.1.1'));
        $this->assertSame(0, $this->users->rows[$this->adminId]['failed_attempts']);
        $this->assertNull($this->users->rows[$this->adminId]['locked_until']);
    }

    public function testThrottledWhenLimiterDenies(): void
    {
        $this->limiter->denyAll = true;

        $this->assertSame(LoginOutcome::Throttled, $this->auth->attempt('admin', self::PASSWORD, '1.1.1.1'));
        $this->assertNull($this->auth->currentAdmin());
    }

    public function testOversizedInputIsRejectedWithoutTouchingTheDatabase(): void
    {
        $this->assertSame(LoginOutcome::Invalid, $this->auth->attempt('admin', str_repeat('a', 5000), '1.1.1.1'));
        $this->assertSame(0, $this->users->rows[$this->adminId]['failed_attempts']);
        $this->assertSame(LoginOutcome::Invalid, $this->auth->attempt(str_repeat('u', 80), self::PASSWORD, '1.1.1.1'));
        $this->assertSame(LoginOutcome::Invalid, $this->auth->attempt('', self::PASSWORD, '1.1.1.1'));
    }

    public function testSessionBecomesInvalidWhenPasswordChangesElsewhere(): void
    {
        $this->auth->attempt('admin', self::PASSWORD, '1.1.1.1');
        $this->users->updatePassword($this->adminId, $this->hasher->hash('a totally different one'), false, gmdate('Y-m-d H:i:s'));

        $this->assertNull($this->newService()->currentAdmin());
    }

    public function testLogoutDestroysSessionAndIsAudited(): void
    {
        $this->auth->attempt('admin', self::PASSWORD, '1.1.1.1');
        $this->auth->logout('1.1.1.1');

        $this->assertNull($this->newService()->currentAdmin());
        $this->assertContains('auth.logout', $this->audit->actions());
    }

    public function testChangePasswordSucceedsAndKeepsCurrentSessionValid(): void
    {
        $this->auth->attempt('admin', self::PASSWORD, '1.1.1.1');

        $error = $this->auth->changePassword($this->adminId, self::PASSWORD, 'brand new strong secret', '1.1.1.1');

        $this->assertNull($error);
        $this->assertTrue($this->hasher->verify('brand new strong secret', (string) $this->users->rows[$this->adminId]['password_hash']));
        $this->assertFalse($this->users->rows[$this->adminId]['must_change_password']);
        $this->assertNotNull($this->newService()->currentAdmin());
        $this->assertContains('auth.password_changed', $this->audit->actions());
    }

    public function testChangePasswordRejectsWrongCurrentSamePasswordAndWeakNew(): void
    {
        $this->assertSame('auth.current_password_wrong', $this->auth->changePassword($this->adminId, 'nope nope nope!', 'brand new strong secret', '1.1.1.1'));
        $this->assertSame('auth.password_same', $this->auth->changePassword($this->adminId, self::PASSWORD, self::PASSWORD, '1.1.1.1'));
        $this->assertSame('password.too_short', $this->auth->changePassword($this->adminId, self::PASSWORD, 'short', '1.1.1.1'));
        $this->assertTrue($this->hasher->verify(self::PASSWORD, (string) $this->users->rows[$this->adminId]['password_hash']));
    }

    public function testChangePasswordIsThrottled(): void
    {
        $this->limiter->denyAll = true;

        $this->assertSame('auth.throttled', $this->auth->changePassword($this->adminId, self::PASSWORD, 'brand new strong secret', '1.1.1.1'));
    }
}
