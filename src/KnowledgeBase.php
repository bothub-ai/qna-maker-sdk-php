<?php

namespace Microsoft\QnAMaker;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class KnowledgeBase
{
    private $client;

    private $conf = array(
        'base_uri' => env('BASE_URI', 'https://westus.api.cognitive.microsoft.com/qnamaker/v4.0/knowledgebases/'),
        'subscription_key' => env('SUBSCRIOTOIN_KEY'),
        'timeout'  => 3.0,
        
    );

    public function __construct($conf, $client = null)
    {
        if (empty($conf) || !isset($conf['subscription_key']) || empty($conf['subscription_key'])) {
            throw new Exception('need: subscription_key');
        }
        $this->conf = array_merge($this->conf, $conf);
        if (empty($client)) {
            $this->client = new Client([
                'base_uri' => $this->conf['base_uri'],
                'timeout' => $this->conf['timeout'],
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->conf['subscription_key'],
                ],
            ]);
        } else {
            $this->client = $client;
        }
    }

    private function requestApi($method, $path, $data =[])
    {
        try {
            $response = $this->client->request($method, $path, [
                'json' => $data,
            ]);
        } catch (RequestException $e) {
            /*
            {
              "error": {
                "extractionStatuses": [
                  {
                    "sourceType": "Url",
                    "externalStatusCode": "NoQuestionsFound",
                    "source": "https://example.com/"
                  }
                ],
                "code": "ExtractionFailed",
                "message": "Unsupported / Invalid url(s). Failed to extract Q&A from the following sources: https://example.com/"
              }
            }
            */
            if ($e->hasResponse()) {
                $body = $e->getResponse()->getBody();
                $tmp = json_decode($body, true);
                $error = isset($tmp['error']) ? $tmp['error'] : [];
                $code = isset($error['code']) && isset(Exception::$codeStr2Num[$error['code']]) ? Exception::$codeStr2Num[$error['code']] : $e->getCode();
                $message = isset($error['code']) && isset($error['message']) ? $error['code'] . ': ' . $error['message'] : $body;
                throw new Exception($message, $code);
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
        return $response;
    }

    /**
     * Create Knowledge Base
     *
     * @example shell curl -X POST -d '{"name": "Learn English", "qnaPairs":[{"answer": "Fine, thanks.", "question": "how are you?"},{"answer": "Nice to meet you, too.", "question": "nice to meet you"}], "urls": ["http://www.seattle.gov/hala/faq"]}' -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/create
     * @return array
     */
    public function create($name, $qnaPairs = [], $urls = [])
    {
        if (empty($name)) {
            throw new Exception('need: name(not empty)', 400);
        }
        $data = [
            'name' => $name,
            'qnaPairs' => $qnaPairs,
            'urls' => $urls,
        ];
        $response = $this->requestApi('POST', 'create', $data);
        return json_decode($response->getBody(), true);
    }

    /**
     * Delete Knowledge Base
     *
     * @example shell curl -X DELETE -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/{knowledgeBaseID}
     * @link https://westus.dev.cognitive.microsoft.com/docs/services/58994a073d9e04097c7ba6fe/operations/58994a073d9e041ad42d9bab
     * @return boolean
     */
    public function delete($id)
    {
        $this->requestApi('DELETE', $id);
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
            throw new Exception('BadArgument: The Question field is required.', 400);
        }
        $data = [
            'question' => $question,
        ];
        if ($top != 1) {
            $data['top'] = $top;
        }
        $response = $this->requestApi('POST', $id . '/generateAnswer', $data);
        $r = json_decode($response->getBody(), true);
        // if not match, QnA response ["answers": [{"answer": "No good match found in the KB", "questions": null, "score": 0}]]
        if (count($r['answers']) == 1 && $r['answers'][0]['score'] == 0) {
            $r['answers'] = [];
        }
        return $r;
    }

    /**
     * Update Knowledge Base
     *
     * @example shell curl -X POST -d '{"add": {"qnaPairs": [{"answer": "Hello, How can I help you?", "question": "Hello" }], "urls": ["http://www.spaceneedle.com/faq/"]}, "delete": {"qnaPairs": [{"answer": "Hello", "question": "hi"}], "urls": ["https://example.com/"]}}' -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/{knowledgeBaseID}
     * @link https://westus.dev.cognitive.microsoft.com/docs/services/58994a073d9e04097c7ba6fe/operations/58994a083d9e041ad42d9bad
     * @return boolean
     */
    public function update($id, $add = [], $delete = [], $publish = false)
    {
        if (empty($add) && empty($delete)) {
            throw new Exception('BadArgument: The Add or Delete field is required.', 400);
        }
        $data = [];
        if (!empty($add)) {
            $data['add'] = $add;
        }
        if (!empty($delete)) {
            $data['delete'] = $delete;
        }
        $this->requestApi('PATCH', $id, $data);
        if ($publish) {
            $this->publish($id);
        }
        return true;
    }

    /**
     * Publish Knowledge Base
     *
     * @example shell curl -X PUT -d '' -H 'Content-Type: application/json' -H 'Ocp-Apim-Subscription-Key: {subscription key}' https://westus.api.cognitive.microsoft.com/qnamaker/v2.0/knowledgebases/{knowledgeBaseID}
     * @link https://westus.dev.cognitive.microsoft.com/docs/services/58994a073d9e04097c7ba6fe/operations/589ab9223d9e041d18da6433
     * @return boolean
     */
    public function publish($id)
    {
        $this->requestApi('PUT', $id);
        return true;
    }

    /**
     * add QnA pairs
     *
     * @return boolean
     */
    public function addQnaPairs($id, $qnaPairs, $publish = false)
    {
        if (empty($qnaPairs)) {
            throw new Exception('BadArgument: The qnaPairs field is required.');
        }
        return $this->update($id, ['qnaPairs' => $qnaPairs], [], $publish);
    }

    /**
     * delete QnA pairs
     *
     * @return boolean
     */
    public function deleteQnaPairs($id, $qnaPairs, $publish = false)
    {
        if (empty($qnaPairs)) {
            throw new Exception('BadArgument: The qnaPairs field is required.');
        }
        return $this->update($id, [], ['qnaPairs' => $qnaPairs], $publish);
    }
}
