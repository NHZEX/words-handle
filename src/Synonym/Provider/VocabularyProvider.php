<?php

namespace zxin\TextWord\Synonym\Provider;

use zxin\TextWord\Synonym\WordText;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DomCrawler\Crawler;
use function array_unique;
use function urlencode;

class VocabularyProvider extends BaseProvider
{
    protected array $definitions = [];

    public static function getType(): string
    {
        return 'vocabulary';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Referer' => 'https://www.vocabulary.com/',
        ];
    }

    protected function asyncQuery(string $word): PromiseInterface
    {
        // 支持原生缓存
        // $date = date_create_from_format(\DateTimeInterface::RFC7231, $response->getHeaderLine('date'));
        $queryStr = urlencode($word);
        return $this->client->getAsync(
            "https://www.vocabulary.com/dictionary/{$queryStr}",
        );
    }

    protected function analyze(StreamInterface $body): array
    {
        $crawler = new Crawler($body->getContents());
        $items = $crawler
            ->filter('div.word-definitions > ol > li')
            ->each(static function (Crawler $node) {
                $spans = $node->filter('div.defContent > dl.instances > span');
                if (0 === $spans->count()) {
                    return null;
                }
                if ('synonyms:' !== $spans->eq(0)->innerText()) {
                    return null;
                }
                // 词性
                $pos = $node->filter('div.definition > div')->innerText();
                // 例句
                $definition = $node->filter('div.definition');
                $definition->children()->each(function (Crawler $crawler) {
                    $crawler->getNode(0)->parentNode->removeChild($crawler->getNode(0));
                });
                $example = $definition->text();
                // 单词
                $words = $spans->eq(1)->filter('a')->each(static function (Crawler $node) {
                    return $node->innerText();
                });
                return [
                    'pos' => $pos,
                    'example' => $example,
                    'words' => $words,
                ];
            });
        $words = [];
        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            $def = [];
            $pos = $item['pos'];
            $def['pos'] = $pos;
            $def['example'] = $item['example'];
            foreach ($item['words'] as $word) {
                $word = new WordText($word, true, $pos);
                $words[] = $word;
                $def['words'][] = $word;
            }
            $this->definitions[] = $def;
        }

        // todo 简单聚合，之后暴力完整建议
        return array_unique($words);
    }

    /**
     * @return array
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }
}
