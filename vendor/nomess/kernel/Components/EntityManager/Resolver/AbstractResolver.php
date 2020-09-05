<?php


namespace Nomess\Components\EntityManager\Resolver;

use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\EntityCache\CacheManager;
use RedBeanPHP\R;
use RedBeanPHP\OODBBean;

abstract class AbstractResolver
{
    protected const ACTION      = 'action';
    protected const COLUMN      = 'column';
    protected const RELATION    = 'relation';
    protected const TYPE        = 'type';
    protected const NAME        = 'name';
    
    /**
     * @Inject()
     */
    protected CacheManager $cacheManager;


    protected function getShortName(string $classname): string
    {
        return substr(strrchr($classname, '\\'), 1);
    }

    protected function getBean(array &$data, ?object $object = NULL): OODBBean
    {
        $table = $data['nomess_table'];
        unset($data['nomess_table']);

        if($object !== NULL){
            if(array_key_exists(get_class($object), Instance::$mapper)) {
                foreach( Instance::$mapper[get_class( $object )] as $key => $array ) {
                    if( in_array( $object, $array, TRUE ) ) {
                        return $array['bean'];
                    }
                }
            }
            
            $bean = R::dispense($table);
            
            Instance::$mapper[get_class($object)][] = [
                'object' => $object,
                'bean' => $bean
            ];
            
            return $bean;
        }
    
        return R::dispense($table);
    }

    protected function getPropertyValue(object $object, string $propertyName)
    {
        $reflectionProperty = new \ReflectionProperty(get_class($object), $propertyName);

        if(!$reflectionProperty->isPublic()){
            $reflectionProperty->setAccessible(TRUE);
        }

        $value = NULL;

        try{
            $value = $reflectionProperty->getValue($object);
        }catch(\Throwable $e){}

        return $value;
    }


    abstract public function resolve(object $object): ?OODBBean;
}
