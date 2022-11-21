<?php

namespace Madmatt\Funnelback\Tests;

use Madmatt\Funnelback\SearchService;
use PHPUnit\Framework\TestCase;

class SearchServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
//        Injector::inst()->registerService(new SearchGatewayFake(), SearchGateway::class);
    }

    public function testSearch(): void
    {
        $this->markTestIncomplete();
//        /** @var SearchService $service */
//        $service = Injector::inst()->get(SearchService::class);
//        $service->search('test');
    }

    public function testSearchPresentsHtmlResultsDifferentlyToFileResults(): void
    {
        $this->markTestIncomplete();
    }

    public function testFormatFileTitle(): void
    {
        $class = SearchService::class;
        $method = 'formatFileTitle';

        // Test a generic file that is exactly 42 MB in size
        $args = ['Filename', 'doc', '44040192'];
        $this->assertSame('Filename (DOC 42MB)', $this->invokeMethod($class, $method, $args));

        // Test that we correctly trim whitespace from the start and end of the filename
        $args = [' test trimmed file  ', 'pdf', '1048576'];
        $this->assertSame('test trimmed file (PDF 1MB)', $this->invokeMethod($class, $method, $args));

        // Test that we correctly show the right filesize unit for different file sizes
        $args = ['test', 'pdf', '512']; // B
        $this->assertSame('test (PDF 512B)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1023']; // B
        $this->assertSame('test (PDF 1023B)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1024']; // KB
        $this->assertSame('test (PDF 1KB)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1025']; // KB
        $this->assertSame('test (PDF 1KB)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1048576']; // MB
        $this->assertSame('test (PDF 1MB)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1048577']; // MB
        $this->assertSame('test (PDF 1MB)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1073741824']; // GB
        $this->assertSame('test (PDF 1GB)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1073741825']; // GB
        $this->assertSame('test (PDF 1GB)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1099511627776']; // TB
        $this->assertSame('test (PDF 1TB)', $this->invokeMethod($class, $method, $args));

        $args = ['test', 'pdf', '1099511627777']; // TB
        $this->assertSame('test (PDF 1TB)', $this->invokeMethod($class, $method, $args));
    }

    private function invokeMethod(string $className, string $methodName, array $args = []): mixed
    {
        $object = new $className;
        $reflectionClass = new \ReflectionClass($className);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
