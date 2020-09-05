<?php


namespace Nomess\Components\EntityManager\Resolver;


use App\Entities\Image;
use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\Cache\Cache;
use Nomess\Components\EntityManager\EntityManager;
use Nomess\Components\EntityManager\Event\CreateEventInterface;
use Nomess\Exception\ORMException;
use RedBeanPHP\OODBBean;

class PersistsResolver extends AbstractResolver
{
    /**
     * @Inject()
     */
    private Cache $cache;

    /**
     * @Inject()
     */
    private CreateEventInterface $createEvent;
    /**
     * @Inject()
     */
    private EntityManager $entityManager;


    private bool $isRelation = FALSE;
    private array $resolved = array();
    
    public function resolve(object $object): ?OODBBean
    {
        $cache = $this->cache->getCache($this->getShortName(get_class($object)), get_class($object), '__UPDATED__');
        return $this->getData($object, $cache);
    }

    protected function getData(object $object, array $cache): OODBBean
    {
        $bean = $this->getBean($cache, $object);
    
        $this->subscribeToMapper($object, $bean);
        $this->resolved[get_class($object)][] = [
            'object' => $object,
            'bean' => $bean
        ];
        
        if(empty($bean->id)){
            $this->createEvent->add($object, $bean);
        }

        foreach($cache as $property){
            $propertyColumn     = $property[self::COLUMN];
            $propertyType       = $property[self::TYPE];
            $propertyRelation   = $property[self::RELATION];
            $propertyName       = $property[self::NAME];
            $propertyValue      = $this->getPropertyValue($object, $propertyName);
            
            if($property[self::ACTION] === 'serialize'){
                $bean->$propertyColumn = (!empty($propertyValue)) ? serialize($propertyValue) : NULL;
            }elseif($property[self::ACTION] === NULL){
                if($propertyName !== 'id' || !empty($propertyValue))
                    $bean->$propertyColumn = $propertyValue;
            }elseif(!empty($propertyRelation)){

                $columnRelation = NULL;
                
                if($propertyRelation['relation'] === 'OneToOneOwner' || $propertyRelation['relation'] === 'OneToMany'){
                    $columnRelation = $this->getRelation($object, $propertyRelation, $propertyValue);
                }elseif($propertyRelation['relation'] === 'OneToOne'){
                    
                    if(!empty($propertyValue)){
                        $reflectionProperty = new \ReflectionProperty(get_class($propertyValue), $propertyRelation['propertyName']);

                        if(!$reflectionProperty->isPublic()) {
                            $reflectionProperty->setAccessible(TRUE);
                        }

                        if($reflectionProperty->getValue($propertyValue) !== $object) {
                            $reflectionProperty->setValue($propertyValue, $object);
                        }

                        if(!$this->entityManager->has($propertyValue)) {
                            $this->entityManager->persists($propertyValue);
                        }
                    }
                }elseif(!empty($propertyValue)){
                    $tmp = array();

                    foreach($propertyValue as $value) {
                        $resultRelation = $this->getRelation($object, $propertyRelation, $value);
                        
                        if(!empty($resultRelation)){
                            $tmp[] = $resultRelation;
                        }
                    }

                    $columnRelation = $tmp;
                }
    
                if(strpos($propertyColumn, 'List') !== FALSE){
                    $bean->$propertyColumn = array();
                }else{
                    unset($bean->$propertyColumn);
                }
    
                if(!empty($columnRelation)){
                    $bean->$propertyColumn = $columnRelation;
                }
            }
        }

        return $bean;
    }


    private function getRelation(object $object, array $relation, $propertyValue): ?OODBBean
    {
        if(!empty($propertyValue)) {

            $classname = get_class($propertyValue);
            if(!empty($this->resolved) && array_key_exists($classname, $this->resolved)){
                foreach($this->resolved[$classname] as $value){
                    if($value['object'] === $propertyValue){
                        return $value['bean'];
                    }
                }
            }

            $bean = $this->resolve($propertyValue);

            return $bean;
        }

        return NULL;
    }
    
    private function subscribeToMapper(object $object, OODBBean $bean): void
    {
        Instance::$mapper[get_class( $object )][] = [
            'object' => $object,
            'bean'   => $bean
        ];
    }
}
