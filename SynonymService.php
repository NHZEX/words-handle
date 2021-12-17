<?php

namespace app\Service\TextWord;

use app\Service\TextWord\Synonym\Provider\BaseProvider;
use app\Service\TextWord\Synonym\Provider\ReversoProvider;
use app\Service\TextWord\Synonym\Provider\ThesaurusProvider;
use app\Service\TextWord\Synonym\Provider\VocabularyProvider;
use app\Service\TextWord\Synonym\WordText;
use GuzzleHttp\Promise\Utils;
use think\Container;
use function array_merge;
use function array_unique;

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
            VocabularyProvider::getType() => new VocabularyProvider(),
            ThesaurusProvider::getType()  => new ThesaurusProvider(),
        ];
    }

    /**
     * @param string $provider
     * @param string $word
     * @return array<WordText>
     */
    protected function query(string $provider, string $word): array
    {
        return $this->provider[$provider]->query($word);
    }

    /**
     * @return array<string, array<WordText>>
     */
    public function queryAll(string $word): array
    {
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
                    $priority1[] = $word;
                } else {
                    $priority2[] = $word;
                }
            }
        }
        return array_unique(array_merge($priority1, $priority2));
    }
}