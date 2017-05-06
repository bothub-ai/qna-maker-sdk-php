<?php

namespace Microsoft\QnAMaker;

use GuzzleHttp\Client;

class KnowledgeBase
{
    private $client;

    private $conf = array(
        'base_uri' => 'https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/',
        'timeout'  => 3.0,
        'subscription_key' => '',
    );

    public function __construct($conf)
    {
        if (empty($conf) || !isset($conf['subscription_key']) || empty($conf['subscription_key'])) {
            throw new Exception('need: subscription_key');
        }
        $this->conf = array_merge($this->conf, $conf);
        $this->client = new Client([
            'base_uri' => $this->conf['base_uri'],
            'timeout' => $this->conf['timeout'],
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->conf['subscription_key'],
            ],
        ]);
    }

    /**
     * Create Knowledge Base
     *
     * @example shell curl -X POST -d '{"name": "Learn English", "qnaPairs":[{"answer": "Fine, thanks.", "question": "how are you?"},{"answer": "Nice to meet you, too.", "question": "nice to meet you"}, "urls": ["http://www.seattle.gov/hala/faq"]]}' -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/create
     * @return array
     */
    public function store($name, $qnaPairs = [], $urls = [])
    {
        if (empty($name)) {
            throw new Exception('need: name(not empty)');
        }
        $data = [
            'name' => $name,
            'qnaPairs' => !empty($qnaPairs) ? $qnaPairs : [
                ['answer' => 'Hello', 'question' => 'hi'],
            ], // QnA bug: return 400 "BadArgument Request Body" when only send "name"，so send one question at here
        ];
        if (!empty($urls)) {
            $data['urls'] = $urls;
        }
        try {
            $response = $this->client->request('POST', 'create', [
                'json' => $data,
            ]);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return json_decode($response->getBody(), true);
    }

    /**
     * Delete Knowledge Base
     *
     * @example shell curl -X DELETE -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/{knowledgeBaseID}
     * @link https://westus.dev.cognitive.microsoft.com/docs/services/58994a073d9e04097c7ba6fe/operations/58994a073d9e041ad42d9bab
     * @return array
     */
    public function delete($id)
    {
        try {
            $this->client->request('DELETE', $id);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return true;
    }
}
