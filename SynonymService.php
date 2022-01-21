<?php

namespace app\Service\TextWord;

use app\Service\TextWord\Dict\DictQueryBadAndWarn;
use app\Service\TextWord\Synonym\Provider\BaseProvider;
use app\Service\TextWord\Synonym\Provider\ReversoProvider;
use app\Service\TextWord\Synonym\Provider\ThesaurusProvider;
use app\Service\TextWord\Synonym\WordText;
use GuzzleHttp\Promise\Utils;
use think\Container;
use function array_merge;
use function array_unique;
use function array_values;
use function strtolower;

class SynonymService
{
    /**
     * @var array<string, BaseProvider>
     */
    protected array $provider;

    public static function instance(): SynonymService
    {
        return Container::getInstance()->make(SynonymService::class);
    }

    public function __construct()
    {
        $this->provider = [
            ReversoProvider::getType()    => new ReversoProvider(),
            ThesaurusProvider::getType()  => new ThesaurusProvider(),
            // VocabularyProvider::getType() => new VocabularyProvider(),
        ];
    }

    /**
     * @param string $provider
     * @param string $word
     * @return array<WordText>
     */
    protected function query(string $provider, string $word): array
    {
        $word = strtolower($word);
        return $this->provider[$provider]->query($word);
    }

    /**
     * @return array<string, array<WordText>>
     */
    public function queryAll(string $word): array
    {
        $word = strtolower($word);
        $queryAll = [];
        foreach ($this->provider as $name => $provider) {
            $queryAll[$name] = $provider->queryAsync($word);
        }
        return Utils::unwrap($queryAll);
    }

    /**
     * @return array<WordText>
     */
    public function queryAllWithAggregation(string $word): array
    {
        $priority1 = [];
        $priority2 = [];
        foreach ($this->queryAll($word) as $words) {
            foreach ($words as $word) {
                if ($word->isRelevant()) {
                    $priority1[strtolower($word)] = $word;
                } else {
                    $priority2[strtolower($word)] = $word;
                }
            }
        }
        return array_values(array_unique(array_merge($priority1, $priority2)));
    }

    /**
     * @return array<WordText>
     */
    public function queryAggregationWithCheckDict(string $word): array
    {
        $items = $this->queryAllWithAggregation($word);
        foreach ($items as $item) {
            $result = DictQueryBadAndWarn::findWord($item->text(), true);
            $item->attr['check'] = empty($result)
                ? null
                : ($result->isBad() ? '违禁词' : ($result->isWarn() ? '敏感词' : null));
        }
        return $items;
    }
}
