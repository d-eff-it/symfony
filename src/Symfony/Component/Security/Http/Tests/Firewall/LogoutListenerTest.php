<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

class LogoutListenerTest extends TestCase
{
    public function testHandleUnmatchedPath()
    {
        [$listener, , $httpUtils, $options] = $this->getListener();

        [$event, $request] = $this->getGetResponseEvent();

        $event->expects($this->never())
            ->method('setResponse');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(false);

        $listener($event);
    }

    public function testHandleMatchedPathWithSuccessHandlerAndCsrfValidation()
    {
        $successHandler = $this->getSuccessHandler();
        $tokenManager = $this->getTokenManager();

        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($successHandler, $tokenManager);

        [$event, $request] = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn($response = new Response());

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener->addHandler($handler);

        $listener($event);
    }

    public function testHandleMatchedPathWithoutSuccessHandlerAndCsrfValidation()
    {
        $successHandler = $this->getSuccessHandler();

        [$listener, $tokenStorage, $httpUtils, $options] = $this->getListener($successHandler);

        [$event, $request] = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn($response = new Response());

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token = $this->getToken());

        $handler = $this->getHandler();
        $handler->expects($this->once())
            ->method('logout')
            ->with($request, $response, $token);

        $tokenStorage->expects($this->once())
            ->method('setToken')
            ->with(null);

        $event->expects($this->once())
            ->method('setResponse')
            ->with($response);

        $listener->addHandler($handler);

        $listener($event);
    }

    public function testSuccessHandlerReturnsNonResponse()
    {
        $this->expectException(\RuntimeException::class);
        $successHandler = $this->getSuccessHandler();

        [$listener, , $httpUtils, $options] = $this->getListener($successHandler);

        [$event, $request] = $this->getGetResponseEvent();

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $successHandler->expects($this->once())
            ->method('onLogoutSuccess')
            ->with($request)
            ->willReturn(null);

        $listener($event);
    }

    public function testCsrfValidationFails()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\LogoutException::class);
        $tokenManager = $this->getTokenManager();

        [$listener, , $httpUtils, $options] = $this->getListener(null, $tokenManager);

        [$event, $request] = $this->getGetResponseEvent();

        $request->query->set('_csrf_token', 'token');

        $httpUtils->expects($this->once())
            ->method('checkRequestPath')
            ->with($request, $options['logout_path'])
            ->willReturn(true);

        $tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(false);

        $listener($event);
    }

    private function getTokenManager()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class)->getMock();
    }

    private function getTokenStorage()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)->getMock();
    }

    private function getGetResponseEvent()
    {
        $event = $this->getMockBuilder(RequestEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($request = new Request());

        return [$event, $request];
    }

    private function getHandler()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Http\Logout\LogoutHandlerInterface::class)->getMock();
    }

    private function getHttpUtils()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Http\HttpUtils::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getListener($successHandler = null, $tokenManager = null)
    {
        $listener = new LogoutListener(
            $tokenStorage = $this->getTokenStorage(),
            $httpUtils = $this->getHttpUtils(),
            $successHandler ?: $this->getSuccessHandler(),
            $options = [
                'csrf_parameter' => '_csrf_token',
                'csrf_token_id' => 'logout',
                'logout_path' => '/logout',
                'target_url' => '/',
            ],
            $tokenManager
        );

        return [$listener, $tokenStorage, $httpUtils, $options];
    }

    private function getSuccessHandler()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface::class)->getMock();
    }

    private function getToken()
    {
        return $this->getMockBuilder(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class)->getMock();
    }
}
