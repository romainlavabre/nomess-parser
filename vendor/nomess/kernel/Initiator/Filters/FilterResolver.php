<?php


namespace Nomess\Initiator\Filters;


use Nomess\Annotations\Inject;
use Nomess\Container\Container;

class FilterResolver
{

    private const CACHE         = ROOT . 'var/cache/filters/filter.php';

    /**
     * @Inject()
     */
    private Container $container;

    public function resolve(string $route): void
    {
        $filters = $this->getCache();

        if($filters === NULL){
            $filters = (new FilterBuilder())->build();
            $this->setCache($filters);
        }

        foreach($filters as $filterName => $regex){
            if(preg_match('/' . $regex . '/', $route)){
                $this->container->get($filterName)->filtrate();
            }
        }
    }

    private function getCache(): ?array
    {
        if(NOMESS_CONTEXT === 'PROD' && file_exists(self::CACHE)){
            return require self::CACHE;
        }

        return NULL;
    }

    private function setCache(array $routes): void
    {
        file_put_contents(self::CACHE, '<?php return unserialize(\'' . serialize($routes) . '\');');
    }
}
