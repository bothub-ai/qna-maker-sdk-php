<?php

namespace Microsoft\QnAMaker\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Microsoft\QnAMaker\KnowledgeBase;
use Microsoft\QnAMaker\Exception;
Use Faker;

class KnowledgeBaseTest extends TestCase
{
    private $kb;
    private $faker;
    private $qnaPairs = [
        ['answer' => 'Fine, thanks.', 'question' => 'how are you?'],
        ['answer' => 'Nice to meet you, too.', 'question' => 'nice to meet you'],
    ];
    private $urls = [
        'http://www.seattle.gov/hala/faq',
        'https://www.uscis.gov/citizenship/teachers/educational-products/100-civics-questions-and-answers-mp3-audio-english-version',
    ];
    private $dataExtractionResults = [];


    protected function setUp()
    {
        $this->kb = new KnowledgeBase([
            'subscription_key' => getenv('QNA_SUB_KEY'),
            'timeout' => 20,
        ]);
        $this->faker = Faker\Factory::create();
        $this->dataExtractionResults = [
            [
                "sourceType" => "Url",
                "extractionStatusCode" => "Success",
                "source" => $this->urls[0],
            ],
            [
                "sourceType" => "Url",
                "extractionStatusCode" => "Success",
                "source" => $this->urls[1],
            ],
        ];
    }

    public function testCreateWithName()
    {
        $r = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__);
        $this->assertArrayHasKey('kbId', $r);

        $this->sleep(); // QnA limit 10 transactions per minute. see https://qnamaker.ai/Documentation/Authentication

        $r = $this->kb->delete($r['kbId']);
        $this->assertTrue($r);

        $this->sleep();
    }

    public function testCreateWithQnaParis()
    {
        $r = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__, $this->qnaPairs);
        $this->assertArrayHasKey('kbId', $r);

        $this->sleep();

        $r = $this->kb->delete($r['kbId']);
        $this->assertTrue($r);

        $this->sleep();
    }

    public function testCreateWithUrls()
    {
        $r = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__, [], $this->urls);
        $this->assertArrayHasKey('kbId', $r);
        $this->assertEquals($this->dataExtractionResults, $r['dataExtractionResults']);

        $this->sleep();

        $r = $this->kb->delete($r['kbId']);
        $this->assertTrue($r);

        $this->sleep();
    }

    public function testCreateWithBadUrls()
    {
        try {
            $this->kb->create($this->faker->word . ' - ' . __FUNCTION__, [], ["https://example.com/"]);
        } catch (Exception $e) {
            $this->assertEquals(Exception::$codeStr2Num['ExtractionFailed'], $e->getCode());
        }

        $this->sleep();
    }

    public function testCreateWithQnaPairsAndUrls()
    {
        $r = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__, $this->qnaPairs, $this->urls);
        $this->assertArrayHasKey('kbId', $r);
        $this->assertEquals($this->dataExtractionResults, $r['dataExtractionResults']);

        $this->sleep();

        $r = $this->kb->delete($r['kbId']);
        $this->assertTrue($r);

        $this->sleep();
    }

    public function testGenerateAnswer()
    {
        $qnaPairs = [
            ['answer' => 'Fine, thanks.', 'question' => 'how are you?'],
            ['answer' => 'Fine, thanks.', 'question' => 'are you ok?'],
            ['answer' => 'Nice to meet you, too.', 'question' => 'Nice to meet you'],
        ];

        $this->sleep();
        $kb = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__, $qnaPairs);
        $this->assertArrayHasKey('kbId', $kb);

        $this->sleep();
        $r = $this->kb->generateAnswer($kb['kbId'], 'how are you');
        $this->assertEquals(1, count($r['answers']));
        $this->assertEquals(['how are you?', 'are you ok?'], $r['answers'][0]['questions']);
        $this->assertEquals('Fine, thanks.', $r['answers'][0]['answer']);

        $this->sleep();
        $r = $this->kb->generateAnswer($kb['kbId'], 'Nice to meet you');
        $this->assertEquals(1, count($r['answers']));
        // test question keep case
        $this->assertEquals(['Nice to meet you'], $r['answers'][0]['questions']);
        // test question keep case
        $this->assertEquals('Nice to meet you, too.', $r['answers'][0]['answer']);

        $this->sleep();
        $r = $this->kb->generateAnswer($kb['kbId'], 'hello', 3);
        $this->assertEquals(0, count($r['answers']));

        $this->sleep();
        $r = $this->kb->delete($kb['kbId']);
        $this->assertTrue($r);

        $this->sleep();
    }

    public function testUpdateAndPublish()
    {
        $qnaPairs = [
            ['answer' => 'Fine, thanks.', 'question' => 'how are you?'],
            ['answer' => 'Fine, thanks.', 'question' => 'are you ok?'],
            ['answer' => 'Nice to meet you, too.', 'question' => 'Nice to meet you'],
        ];
        $urls = [$this->urls[0]];

        $this->sleep();

        $kb = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__, $qnaPairs, $urls);
        $this->assertArrayHasKey('kbId', $kb);

        $this->sleep();

        $r = $this->kb->generateAnswer($kb['kbId'], 'how are you');
        $this->assertEquals(1, count($r['answers']));
        $this->assertEquals(['how are you?', 'are you ok?'], $r['answers'][0]['questions']);

        $add = [
            'qnaPairs' => [
                [
                    'answer' => 'Hello, How can I help you?',
                    'question' => 'Hello',
                ],
            ],
            'urls' => [$this->urls[1]],
        ];
        $delete = [
            'qnaPairs' => [['answer' => 'Fine, thanks.', 'question' => 'are you ok?']],
            'urls' => [$this->urls[0]],
        ];

        $this->sleep();

        $r = $this->kb->update($kb['kbId'], $add, $delete);
        $this->assertTrue($r);

        $this->sleep();

        $r = $this->kb->publish($kb['kbId']);
        $this->assertTrue($r);

        $this->sleep();

        $r = $this->kb->generateAnswer($kb['kbId'], 'Hello');
        $this->assertEquals(1, count($r['answers']));


        $this->sleep();

        $r = $this->kb->generateAnswer($kb['kbId'], 'are you ok?');
        $this->assertEquals(0, count($r['answers']));

        $this->sleep();

        $r = $this->kb->delete($kb['kbId']);
        $this->assertTrue($r);

        $this->sleep();
    }

    public function testAddQnaPairs()
    {

        $this->sleep();

        $kb = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__);
        $this->assertArrayHasKey('kbId', $kb);

        $qnaPairs = [
            [
                'answer' => 'Hello, How can I help you?',
                'question' => 'Hello',
            ],
        ];

        $this->sleep();

        $r = $this->kb->addQnaPairs($kb['kbId'], $qnaPairs, true);
        $this->assertTrue($r);

        $r = $this->kb->generateAnswer($kb['kbId'], 'Hello');
        $this->assertEquals(1, count($r['answers']));
        $this->assertEquals([$qnaPairs[0]['question']], $r['answers'][0]['questions']);
        $this->assertEquals($qnaPairs[0]['answer'], $r['answers'][0]['answer']);

        $this->sleep();

        $r = $this->kb->delete($kb['kbId']);
        $this->assertTrue($r);

        $this->sleep();
    }

    public function testDeleteQnaPairs()
    {
        $qnaPairs = [
            ['answer' => 'Fine, thanks.', 'question' => 'how are you?'],
            ['answer' => 'Fine, thanks.', 'question' => 'are you ok?'],
            ['answer' => 'Nice to meet you, too.', 'question' => 'Nice to meet you'],
        ];

        $this->sleep();

        $kb = $this->kb->create($this->faker->word . ' - ' . __FUNCTION__, $qnaPairs);
        $this->assertArrayHasKey('kbId', $kb);

        $deleteQnaPairs = [
            $qnaPairs[1],
        ];

        $this->sleep();

        $r = $this->kb->deleteQnaPairs($kb['kbId'], $deleteQnaPairs, true);
        $this->assertTrue($r);

        $this->sleep();

        $r = $this->kb->generateAnswer($kb['kbId'], 'how are you');
        $this->assertEquals(1, count($r['answers']));
        $this->assertEquals(['how are you?'], $r['answers'][0]['questions']);

        $this->sleep();

        $r = $this->kb->delete($kb['kbId']);
        $this->assertTrue($r);
    }

    private function sleep($seconds = 0)
    {
        fwrite(STDERR, 'sleep ' . $seconds . 's' . "\n");
        sleep($seconds);
    }
}
