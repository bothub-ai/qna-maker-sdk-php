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
    public function create($name, $qnaPairs = [], $urls = [])
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

    /**
     * Generate answer
     *
     * @example shell curl -X POST -d '{"question": "hi"}' -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/{knowledgeBaseID}/generateAnswer
     * @link https://westus.dev.cognitive.microsoft.com/docs/services/58994a073d9e04097c7ba6fe/operations/58994a073d9e041ad42d9ba9
     * @return array
     */
    public function generateAnswer($id, $question, $top = 1)
    {
        if (empty($question)) {
            throw new Exception('BadArgument: The Question field is required.');
        }
        $data = [
            'question' => $question,
        ];
        if ($top != 1) {
            $data['top'] = $top;
        }
        try {
            $response = $this->client->request('POST', $id . '/generateAnswer', [
                'json' => $data,
            ]);
            $r = json_decode($response->getBody(), true);
            // if not match, QnA response ["answers": [{"answer": "No good match found in the KB", "questions": null, "score": 0}]]
            if (count($r['answers']) == 1 && $r['answers'][0]['score'] == 0) {
                $r['answers'] = [];
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $r;
    }

    /**
     * Update Knowledge Base
     *
     * @example shell curl -X POST -d '{"add": {"qnaPairs": [{"answer": "Hello, How can I help you?", "question": "Hello" }], "urls": ["http://www.spaceneedle.com/faq/"]}, "delete": {"qnaPairs": [{"answer": "Hello", "question": "hi"}], "urls": ["https://example.com/"]}}' -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/{knowledgeBaseID}
     * @link https://westus.dev.cognitive.microsoft.com/docs/services/58994a073d9e04097c7ba6fe/operations/58994a083d9e041ad42d9bad
     * @return array
     */
    public function update($id, $add = [], $delete = [])
    {
        if (empty($add) && empty($delete)) {
            throw new Exception('BadArgument: The Add or Delete field is required.');
        }
        $data = [];
        if (!empty($add)) {
            $data['add'] = $add;
        }
        if (!empty($delete)) {
            $data['delete'] = $delete;
        }
        try {
            $this->client->request('PATCH', $id, [
                'json' => $data,
            ]);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return true;
    }

    /**
     * Update Knowledge Base
     *
     * @example shell curl -X PUT -d '' -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/{knowledgeBaseID}
     * @link https://westus.dev.cognitive.microsoft.com/docs/services/58994a073d9e04097c7ba6fe/operations/589ab9223d9e041d18da6433
     * @return array
     */
    public function publish($id)
    {
        try {
            $this->client->request('PUT', $id);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return true;
    }
}
