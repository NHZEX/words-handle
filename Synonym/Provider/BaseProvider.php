<?php

namespace app\Service\TextWord\Synonym\Provider;

use app\Model\SynonymStoreModel;
use app\Service\TextWord\Synonym\WordText;
use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use JsonMapper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function date_create_from_format;
use function dump;
use function sys_get_temp_dir;
use function tempnam;

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
        $this->client = new Client([
            RequestOptions::TIMEOUT         => 10.0,
            RequestOptions::VERIFY          => CaBundle::getSystemCaRootBundlePath(),
            RequestOptions::HEADERS         => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36 Edg/96.0.1054.53',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
            ] + $this->defaultHeaders(),
            RequestOptions::DEBUG           => $this->debug,
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 3,
            ],
            RequestOptions::PROXY => 'http://192.168.10.1:10901',
        ]);
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
                SynonymStoreModel::writeWord(static::getType(), $word, [
                    'ver' => 1,
                    'words' => $words,
                ]);
                return $words;
            });
    }
}
