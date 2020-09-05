<?php


namespace Nomess\Components\EntityManager\EntityCache;


use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\Cache\Cache;

class Writer
{
    
    private const INDEX_CE_RELATION          = 'relation';
    private const INDEX_CE_TYPE              = 'type';
    private const INDEX_CE_NAME              = 'name';
    private const INDEX_CLASSNAME            = 'classname';
    private const INDEX_PROPERTIES           = 'properties';
    private const INDEX_TYPE                 = 'property_type';
    private const INDEX_DEPENDENCY_CLASSNAME = 'dependency_classname';
    private const INDEX_VALUE                = 'property_value';
    /**
     * @Inject()
     */
    private Repository $repository;
    /**
     * @Inject()
     */
    private Cache      $cache;
    private bool       $isNotified      = FALSE;
    private array      $propertyVisited = array();
    
    
    public function writerNotifiedEvent( bool $status ): void
    {
        $this->isNotified = TRUE;
        
        if( $status ) {
            
            foreach( $this->repository->getStore() as $classname => $data ) {
                foreach( $data as $object ) {
                    $this->writeObject( $object );
                }
            }
            
            foreach( $this->repository->getRemoved() as $filename ) {
                $this->repository->removeFile( $filename );
            }
            
            if( $this->repository->isStatusChanged() ) {
                $this->writeStatus();
            }
        }
    }
    
    
    public function writerDestructEvent(): void
    {
        if( !$this->isNotified ) {
            
            foreach( $this->repository->getCloned() as $classname => $data ) {
                foreach( $data as $object ) {
                    $this->writeObject( $object );
                }
            }
            
            if( $this->repository->isStatusChanged() ) {
                $this->writeStatus();
            }
        }
    }
    
    
    private function writeStatus(): void
    {
        file_put_contents( Repository::PATH_STATUS, serialize( $this->repository->getStatus() ), LOCK_EX );
    }
    
    
    private function writeObject( object $object ): void
    {
        $classname = get_class( $object );
        $cache     = $this->getCacheEntityManager( $classname );
        
        unset( $cache['nomess_table'], $cache['nomess_filectime'] );
        
        $data                        = array();
        $data[self::INDEX_CLASSNAME] = $classname;
        
        foreach( $cache as $propertyColumn => $propertyData ) {
            $propertyRelation = $propertyData[self::INDEX_CE_RELATION];
            $propertyName     = $propertyData[self::INDEX_CE_NAME];
            $propertyType     = $propertyData[self::INDEX_CE_TYPE];
            
            $data[self::INDEX_PROPERTIES][$propertyName] = [
                self::INDEX_TYPE                 => $propertyType,
                self::INDEX_VALUE                => $this->getValue( $object, $propertyName, !empty( $propertyRelation ) ? TRUE : FALSE ),
                self::INDEX_DEPENDENCY_CLASSNAME => !empty( $propertyRelation ) ? $propertyRelation['type'] : NULL
            ];
        }
        
        file_put_contents( $this->repository->getFilename( $classname, $object->getId() ), serialize( $data ), LOCK_EX );
    }
    
    
    /**
     * Return the associate cache to classname (in __SELECT__ mode) building
     * by entityManager
     *
     * @param string $classname
     * @return array
     */
    private function getCacheEntityManager( string $classname ): array
    {
        return $this->cache->getCache( substr( strrchr( $classname, '\\' ), 1 ), $classname, '__SELECT__' );
    }
    
    
    private function getValue( object $object, string $propertyName, bool $relation )
    {
        $reflectionProperty = $this->getReflectionProperty( get_class( $object ), $propertyName );
        
        if( $reflectionProperty->isInitialized( $object ) && !is_null( $value = $reflectionProperty->getValue( $object ) ) ) {
            
            if( $relation ) {
                if( is_array( $value ) ) {
                    $list = array();
                    
                    foreach( $value as $object ) {
                        if(is_object($object)) {
                            $list[] = $object->getId();
                        }
                    }
                    
                    return $list;
                }
                
                return $value->getId();
            }
            
            return $value;
        }
        
        return NULL;
    }
    
    
    private function getReflectionProperty( string $classname, string $propertyname ): \ReflectionProperty
    {
        if( isset( $this->propertyVisited[$classname][$propertyname] ) ) {
            return $this->propertyVisited[$classname][$propertyname];
        }
        
        $reflectionProperty = new \ReflectionProperty( $classname, $propertyname );
        
        if( !$reflectionProperty->isPublic() ) {
            $reflectionProperty->setAccessible( TRUE );
        }
        
        return $this->propertyVisited[$classname][$propertyname] = $reflectionProperty;
    }
}
