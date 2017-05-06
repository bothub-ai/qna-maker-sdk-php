<?php

namespace Microsoft\QnAMaker\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Microsoft\QnAMaker\KnowledgeBase;

class KnowledgeBaseTest extends TestCase
{
    private $kb;

    protected function setUp()
    {
        $this->kb = new KnowledgeBase([
            'subscription_key' => getenv('QNA_SUB_KEY'),
            'timeout' => 20,
        ]);
    }

    public function testStore()
    {
        $name = 'Learn English';
        $qnaPairs = [
            ['answer' => 'Fine, thanks.', 'question' => 'how are you?'],
            ['answer' => 'Nice to meet you, too.', 'question' => 'nice to meet you'],
        ];
        $urls = ['http://www.seattle.gov/hala/faq', 'https://example.com/'];
        $r = $this->kb->store($name . ' - only name');
        $this->assertArrayHasKey('kbId', $r);

        $r = $this->kb->store($name . ' - name and qnaParis', $qnaPairs);
        $this->assertArrayHasKey('kbId', $r);

        $r = $this->kb->store($name . ' - name and urls', [], $urls);
        $this->assertArrayHasKey('kbId', $r);
        $dataExtractionResults = [
            [
                "sourceType" => "Url",
                "extractionStatusCode" => "Success",
                "source" => "http://www.seattle.gov/hala/faq"
            ],
            [
                "sourceType" => "Url",
                "extractionStatusCode" => "NoQuestionsFound",
                "source" => "https://example.com/"
            ],
        ];
        $this->assertEquals($dataExtractionResults, $r['dataExtractionResults']);

        $r = $this->kb->store($name . ' - name, qnaPairs and urls', $qnaPairs, $urls);
        $this->assertArrayHasKey('kbId', $r);
        $this->assertEquals($dataExtractionResults, $r['dataExtractionResults']);
    }

    public function testDelete()
    {
        $r = $this->kb->store('Nine Days\' Wonder');
        $this->assertArrayHasKey('kbId', $r);

        $r = $this->kb->delete($r['kbId']);
        $this->assertTrue($r);
    }
}
