<?php

namespace app\Service\TextWord\Synonym\Provider;

use app\Model\SynonymStoreModel;
use app\Service\TextWord\Synonym\WordText;
use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\RequestOptions;
use JsonMapper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

abstract class BaseProvider
{
    protected bool $debug = false;

    protected Client $client;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
        $this->initClient();
    }

    protected function initClient()
    {
        // todo 自动检查可用编码 https://stackoverflow.com/a/52035972/10242420
        $config = [
            RequestOptions::TIMEOUT         => 10.0,
            RequestOptions::VERIFY          => CaBundle::getSystemCaRootBundlePath(),
            RequestOptions::HEADERS         => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36 Edg/96.0.1054.53',
                    'Accept-Encoding' => 'gzip, deflate', // br
                    'Accept-Language' => 'en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
                ] + $this->defaultHeaders(),
            RequestOptions::DEBUG           => $this->debug,
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 3,
            ],
        ];
        if ($proxy = \Env\env('HTTP_PROXY')) {
            $config[RequestOptions::PROXY] = $proxy;
        }
        $this->client = new Client($config);
    }

    abstract public static function getType(): string;

    abstract protected function defaultHeaders(): array;

    abstract protected function asyncQuery(string $word): PromiseInterface;

    /**
     * @return array<int, WordText>
     */
    abstract protected function analyze(StreamInterface $body): array;

    /**
     * @return array<int, WordText>
     */
    public function query(string $word): array
    {
        return $this->queryAsync($word)->wait();
    }

    public function queryAsync(string $word): PromiseInterface
    {
        $store = SynonymStoreModel::queryWord(static::getType(), $word);
        if (!empty($store)) {
            // 可考虑收录完整的查询数据 WordDefinition
            $mapper = new JsonMapper();
            $mapper->bIgnoreVisibility = true;
            $output = $mapper->mapArray($store->store->words, [], WordText::class);
            return new FulfilledPromise($output);
        }

        return $this
            ->asyncQuery($word)
            ->then(function (ResponseInterface $response) {
                $body = $response->getBody();
                return $this->analyze($body);
            })
            ->then(function (array $words) use ($word) {
                if (empty($word)) {
                    // todo redis 缓冲
                    return $words;
                }
                SynonymStoreModel::writeWord(static::getType(), $word, [
                    'ver' => 1,
                    'words' => $words,
                ]);
                return $words;
            })
            ->then(null, function (RequestException $exception) {
                if (!$exception->hasResponse()) {
                    return new RejectedPromise($exception);
                }
                if (404 === $exception->getResponse()->getStatusCode()) {
                    return [];
                }
                return new RejectedPromise($exception);
            });
    }
}
