<?php

namespace app\Service\TextWord\Synonym\Provider;

use app\Service\TextWord\Synonym\WordText;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DomCrawler\Crawler;
use function trim;
use function urlencode;

class ThesaurusProvider extends BaseProvider
{

    public static function getType(): string
    {
        return 'thesaurus';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Referer' => 'https://www.thesaurus.com/',
        ];
    }

    protected function asyncQuery(string $word): PromiseInterface
    {
        // 支持原生缓存
        $queryStr = urlencode($word);
        return $this->client->getAsync(
            "https://www.thesaurus.com/browse/{$queryStr}",
        );
    }

    protected function analyze(StreamInterface $body): array
    {
        $firstClass = null;
        $changeCount = 0;
        $crawler = new Crawler($body->getContents());
        return $crawler
            ->filter('section.MainContentContainer > section > div > div > ul > li > a')
            ->each(static function (Crawler $node) use (&$firstClass, &$changeCount) {
                $class = $node->attr('class');
                if ($firstClass === null) {
                    $firstClass = $class;
                } elseif ($class !== $firstClass) {
                    $changeCount++;
                }
                return new WordText($node->text(), $changeCount === 0);
            });
    }
}
