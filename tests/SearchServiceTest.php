<?php

namespace Madmatt\Funnelback\Tests;

use Madmatt\Funnelback\SearchService;
use SilverStripe\Core\Injector\Injector;
use Madmatt\Funnelback\Fakes\SearchGatewayFake;
use Madmatt\Funnelback\SearchGateway;
use SilverStripe\Dev\FunctionalTest;

class SearchServiceTest extends FunctionalTest
{
    /**
     * Undocumented variable
     *
     * @var SearchGatewayFake
     */
    private $fakeGateWay;

    public function setUp(): void
    {
        parent::setUp();
        $this->fakeGateWay = new SearchGatewayFake();
        Injector::inst()->registerService($this->fakeGateWay, SearchGateway::class);
    }


    /**
     * Test that the service service formats the results into the format required by the front end.
     * Non-html file types should have file size be part of the title.
     *
     * @return void
     */
    public function testSearchFormatResultsCorrectly(): void
    {
        $mockWebPageResult =  [
            'fileType' => 'html',
            'title' => 'COVID-19',
            'fileSize' => 52162,
            'liveUrl' => 'test url 1',
            'summary' => 'fake summary 1'
        ];
        $mockPdfResult = [
            'fileType' => 'pdf',
            'title' => 'COVID-29',
            'fileSize' => 62162,
            'liveUrl' => 'test url 2',
            'summary' => 'fake summary 2'
        ];
        $this->fakeGateWay->setReturnedResults([
            'results' => [
                $mockWebPageResult,
                $mockPdfResult
            ],
            'resultsSummary' => ['totalMatching' => 5]
        ]);

        $service = Injector::inst()->get(SearchService::class);
        $list = $service->search('test');

        $this->assertSame([
            'Link' => 'test url 1',
            'Title' => 'COVID-19',
            'Summary' => 'fake summary 1',
            'FileType' => 'html'
        ], $list[0]);

        $this->assertSame([
            'Link' => 'test url 2',
            'Title' => 'COVID-29 (PDF 61KB)',
            'Summary' => 'fake summary 2',
            'FileType' => 'pdf'
        ], $list[1]);
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
        $object = new $className();
        $reflectionClass = new \ReflectionClass($className);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
