<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Session;

final class SessionTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItStartsLazilyAndCanReleaseItsLock(): void
    {
        ini_set('session.use_cookies', '0');
        $session = $this->session();

        self::assertFalse($session->isStarted());

        $session->set('user_id', 42);

        self::assertTrue($session->isStarted());
        self::assertSame(42, $session->get('user_id'));

        $session->close();

        self::assertFalse($session->isStarted());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFlashDataLivesForOneFollowingRequest(): void
    {
        ini_set('session.use_cookies', '0');
        $firstRequest = $this->session();
        $firstRequest->flash('success', 'Saved');
        $id = $firstRequest->id();
        $firstRequest->close();

        session_id($id);
        $secondRequest = $this->session();
        self::assertSame('Saved', $secondRequest->peekFlash('success'));
        self::assertSame('Saved', $secondRequest->pullFlash('success'));
        self::assertNull($secondRequest->pullFlash('success'));
        $secondRequest->close();

        session_id($id);
        $thirdRequest = $this->session();
        self::assertNull($thirdRequest->pullFlash('success'));
        $thirdRequest->destroy();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItRegeneratesAndInvalidatesTheSession(): void
    {
        ini_set('session.use_cookies', '0');
        $session = $this->session();
        $session->set('authenticated', true);
        $firstId = $session->id();

        $session->regenerate();
        $secondId = $session->id();

        self::assertNotSame($firstId, $secondId);

        $session->invalidate();

        self::assertSame([], $session->all());
        self::assertNotSame($secondId, $session->id());
        $session->destroy();
    }

    private function session(): Session
    {
        return new Session([
            'name' => 'WPSMICROTEST',
            'secure' => false,
            'http_only' => true,
            'same_site' => 'Strict',
        ]);
    }
}
