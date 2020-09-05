<?php


namespace Nomess\Components\EntityManager\EntityCache;


use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\EntityManagerInterface;

class Reader
{
    
    private const INDEX_CLASSNAME            = 'classname';
    private const INDEX_PROPERTIES           = 'properties';
    private const INDEX_DEPENDENCY_CLASSNAME = 'dependency_classname';
    private const INDEX_VALUE                = 'property_value';
    /**
     * @Inject()
     */
    private Repository $repository;
    /**
     * @Inject()
     */
    private EntityManagerInterface $entityManager;
    private array                  $propertyVisited = array();
    
    
    public function read( string $classname, int $id ): ?object
    {
        if( $this->repository->storeHas( $classname, $id ) ) {
            return $this->repository->getToStore( $classname, $id );
        }
        
        $content = $this->getContent( $this->repository->getFilename( $classname, $id ) );
        
        if( is_null( $content ) ) {
            return NULL;
        }
        
        $instance = $this->getInstance( $content );
        
        $this->setId( $instance, $id );
        $this->repository->addInStore( $instance );
        $this->hydrateProperties( $content, $instance );
        
        return $instance;
    }
    
    
    private function hydrateProperties( array $content, object $instance ): void
    {
        foreach( $content[self::INDEX_PROPERTIES] as $propertyName => $metadata ) {
            if( $propertyName !== 'id' ) {
                $reflectionProperty = $this->getReflectionProperty( $content[self::INDEX_CLASSNAME], $propertyName );
                
                if( is_null( $metadata[self::INDEX_DEPENDENCY_CLASSNAME] ) ) {
                    if($reflectionProperty->getType()->getName() === 'array'){
                        if(!is_null($metadata[self::INDEX_VALUE])){
                            $reflectionProperty->setValue($instance, $metadata[self::INDEX_VALUE]);
                        }else{
                            $reflectionProperty->setValue($instance, []);
                        }
                    }else {
                        $reflectionProperty->setValue( $instance, $metadata[self::INDEX_VALUE] );
                    }
                } else {
                    
                    if( $reflectionProperty->getType()->getName() === 'array' ) {
                        
                        if( !empty( $metadata[self::INDEX_VALUE] ) ) {
                            $list = array();
                            
                            foreach( $metadata[self::INDEX_VALUE] as $value ) {
                                $entity = $this->read( $metadata[self::INDEX_DEPENDENCY_CLASSNAME], $value );
                                
                                if( is_null( $entity ) ) {
                                    $entity = $this->entityManager->find( $metadata[self::INDEX_DEPENDENCY_CLASSNAME], $value );
                                }
    
                                if(!empty($entity)) {
                                    $list[] = $entity;
                                }
                            }
                            
                            $reflectionProperty->setValue( $instance, $list );
                        }
                    } else {
                        if( !is_null( $metadata[self::INDEX_VALUE] ) ) {
                            
                            $entity = $this->read( $metadata[self::INDEX_DEPENDENCY_CLASSNAME], $metadata[self::INDEX_VALUE] );
                            
                            if( is_null( $entity ) ) {
                                $entity = $this->entityManager->find( $metadata[self::INDEX_DEPENDENCY_CLASSNAME], $metadata[self::INDEX_VALUE] );
                            }
                            
                            $reflectionProperty->setValue( $instance, $entity );
                        } else {
                            $reflectionProperty->setValue( $instance, NULL );
                        }
                    }
                }
            }
        }
    }
    
    
    private function getContent( string $filename ): ?array
    {
        if( file_exists( $filename ) ) {
            return unserialize( file_get_contents( $filename ) );
        }
        
        return NULL;
    }
    
    
    private function getInstance( array $content ): object
    {
        return new $content[self::INDEX_CLASSNAME]();
    }
    
    
    private function setId( object $object, int $id ): void
    {
        $this->getReflectionProperty( get_class( $object ), 'id' )->setValue( $object, $id );
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
