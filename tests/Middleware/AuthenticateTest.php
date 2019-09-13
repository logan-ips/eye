<?php

namespace Eyewitness\Eye\Test\Middleware;

use Illuminate\Http\Request;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Http\Middleware\Authenticate;

class AuthenticateTest extends TestCase
{
    protected $request;

    protected $ar;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;
        $this->ar = new Authenticate;
    }

    public function test_prevents_unauthorized_access()
    {
        session()->forget('eyewitness:auth');

        $response = $this->ar->handle($this->request, function() {});
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_allows_authorized_access_when_session_is_set()
    {
        session()->put('eyewitness:auth', 1);

        $response = $this->ar->handle($this->request, function() {});
        $this->assertNull($response);
    }
}
