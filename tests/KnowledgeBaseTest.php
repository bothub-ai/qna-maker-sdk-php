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

    public function testCreate()
    {
        $name = 'Learn English';
        $qnaPairs = [
            ['answer' => 'Fine, thanks.', 'question' => 'how are you?'],
            ['answer' => 'Nice to meet you, too.', 'question' => 'nice to meet you'],
        ];
        $urls = ['http://www.seattle.gov/hala/faq', 'https://example.com/'];
        $r = $this->kb->create($name . ' - only name');
        $this->assertArrayHasKey('kbId', $r);

        $r = $this->kb->create($name . ' - name and qnaParis', $qnaPairs);
        $this->assertArrayHasKey('kbId', $r);

        $r = $this->kb->create($name . ' - name and urls', [], $urls);
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

        $r = $this->kb->create($name . ' - name, qnaPairs and urls', $qnaPairs, $urls);
        $this->assertArrayHasKey('kbId', $r);
        $this->assertEquals($dataExtractionResults, $r['dataExtractionResults']);
    }

    public function testDelete()
    {
        $r = $this->kb->create('Nine Days\' Wonder');
        $this->assertArrayHasKey('kbId', $r);

        $r = $this->kb->delete($r['kbId']);
        $this->assertTrue($r);
    }

    public function testGenerateAnswer()
    {
        $name = 'Learn English - testGenerateAnswer';
        $qnaPairs = [
            ['answer' => 'Fine, thanks.', 'question' => 'how are you?'],
            ['answer' => 'Fine, thanks.', 'question' => 'are you ok?'],
            ['answer' => 'Nice to meet you, too.', 'question' => 'Nice to meet you'],
        ];
        $kb = $this->kb->create($name, $qnaPairs);
        $this->assertArrayHasKey('kbId', $kb);

        $r = $this->kb->generateAnswer($kb['kbId'], 'how are you');
        $this->assertEquals(1, count($r['answers']));
        $this->assertEquals(['how are you?', 'are you ok?'], $r['answers'][0]['questions']);
        $this->assertEquals('Fine, thanks.', $r['answers'][0]['answer']);

        $r = $this->kb->generateAnswer($kb['kbId'], 'Nice to meet you');
        $this->assertEquals(1, count($r['answers']));
        // test question always be converted to low case
        $this->assertEquals([strtolower('Nice to meet you')], $r['answers'][0]['questions']);
        // test question keep case
        $this->assertEquals('Nice to meet you, too.', $r['answers'][0]['answer']);

        $r = $this->kb->generateAnswer($kb['kbId'], 'hello', 3);
        $this->assertEquals(0, count($r['answers']));

        $r = $this->kb->delete($kb['kbId']);
        $this->assertTrue($r);
    }
}
