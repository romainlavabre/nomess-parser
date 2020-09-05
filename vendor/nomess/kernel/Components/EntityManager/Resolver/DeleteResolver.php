<?php


namespace Nomess\Components\EntityManager\Resolver;


use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\Cache\Cache;
use Nomess\Components\EntityManager\Container\Container;
use RedBeanPHP\OODBBean;

class DeleteResolver extends AbstractResolver
{
    /**
     * @Inject()
     */
    private Cache $cache;
    
    public function resolve(object $object): ?OODBBean
    {
        $cache = $this->cache->getCache($this->getShortName(get_class($object)), get_class($object), '__UPDATED__');
        return $this->getData($object, $cache);
    }

    protected function getData(object $object, array $cache): OODBBean
    {
        $this->cacheManager->remove($object);
        $bean = $this->getBean($cache, $object);
        $bean->id = $object->getId();
        return $bean;
    }
    
}
