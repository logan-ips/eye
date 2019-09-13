<?php

namespace Eyewitness\Eye\Test\Tools;

use Mockery;
use Eyewitness\Eye\Test\TestCase;
use Eyewitness\Eye\Tools\BladeDirectives;

class BladeDirectivesTest extends TestCase
{
    protected $blade;

    public function setUp(): void
    {
        parent::setUp();

        $this->blade = Mockery::mock('\Eyewitness\Eye\Tools\BladeDirectives[loadFile]');
    }

    public function test_generates_icon_string_with_defaults()
    {
        $this->blade->shouldReceive('loadFile')->once()->andReturn('<svg width="32" height="32"></svg>');

        $string = $this->blade->getIconString('example');

        $this->assertStringContainsString('<svg', $string);
        $this->assertStringContainsString('width="32" height="32"', $string);
        $this->assertStringContainsString('class=""', $string);
    }

    public function test_generates_icon_string_with_overrides()
    {
        $this->blade->shouldReceive('loadFile')->once()->andReturn('<svg width="32" height="32"></svg>');

        $string = $this->blade->getIconString('example', 'test', 16, 48);

        $this->assertStringContainsString('<svg', $string);
        $this->assertStringContainsString('width="16" height="48"', $string);
        $this->assertStringContainsString('class="test"', $string);
    }
}
