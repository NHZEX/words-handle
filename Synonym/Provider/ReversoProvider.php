<?php

namespace app\Service\TextWord\Synonym\Provider;

use app\Service\TextWord\Synonym\WordText;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DomCrawler\Crawler;
use function array_unique;
use function date_create_from_format;
use function dump;
use function str_contains;
use function urlencode;

class ReversoProvider extends BaseProvider
{
    public static function getType(): string
    {
        return 'reverso';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Referer' => 'https://synonyms.reverso.net/synonym/en/description',
        ];
    }

    protected function asyncQuery(string $word): PromiseInterface
    {
        // 不支持原生缓存
        $queryStr = urlencode($word);
        return $this->client->getAsync(
            "https://synonyms.reverso.net/synonym/en/{$queryStr}",
            [
                RequestOptions::HEADERS => [],
            ],
        );
    }

    protected function analyze(StreamInterface $body): array
    {
        $crawler = new Crawler($body->getContents());
        $words = $crawler
            ->filter('ul.word-box > li > a.synonym')
            ->each(function (Crawler $node) {
                return new WordText($node->innerText(), str_contains($node->attr('class'), 'relevant'));
            });

        return array_unique($words);
    }
}
