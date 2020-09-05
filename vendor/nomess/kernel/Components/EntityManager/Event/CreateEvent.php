<?php

namespace Nomess\Components\EntityManager\Event;

use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\Container\Container;
use Nomess\Components\EntityManager\EntityCache\CacheManager;
use RedBeanPHP\OODBBean;

class CreateEvent implements CreateEventInterface
{
    
    /**
     * @Inject()
     */
    private CacheManager $cacheManager;
    private array $pile = array();
    
    public function add(object $target, OODBBean $bean): void
    {
        $this->pile[] = [
            'object' => $target,
            'bean' => $bean
        ];
    }

    public function execute(): void
    {
        if(!empty($this->pile)){

            $i = 0;
            foreach($this->pile as $value){

                if(!empty($value['bean']->id)) {
                    $reflectionProperty = new \ReflectionProperty(get_class($value['object']), 'id');

                    if(!$reflectionProperty->isPublic()) {
                        $reflectionProperty->setAccessible(TRUE);
                    }

                    $reflectionProperty->setValue($value['object'], $value['bean']->id);
                    $this->cacheManager->add($value['object']);
                    unset($this->pile[$i]);
                }

                $i++;
            }
        }
    }
}
