<?php

namespace app\Service\TextWord;

use app\Service\TextWord\Synonym\Provider\BaseProvider;
use app\Service\TextWord\Synonym\Provider\ReversoProvider;
use app\Service\TextWord\Synonym\Provider\ThesaurusProvider;
use app\Service\TextWord\Synonym\Provider\VocabularyProvider;
use GuzzleHttp\Promise\Utils;
use think\Container;

class SynonymService
{
    /** @var array<BaseProvider> */
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

    protected function query(string $provider, string $word): array
    {
        return $this->provider[$provider]->query($word);
    }

    public function queryAll(string $word): array
    {
        $queryAll = [];
        foreach ($this->provider as $name => $provider) {
            $queryAll[$name] = $provider->queryAsync($word);
        }
        return Utils::unwrap($queryAll);
    }
}
