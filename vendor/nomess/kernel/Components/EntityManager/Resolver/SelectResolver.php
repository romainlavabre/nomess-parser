<?php


namespace Nomess\Components\EntityManager\Resolver;


use Nomess\Annotations\Inject;
use Nomess\Components\EntityManager\Cache\Cache;
use Nomess\Components\EntityManager\EntityCache\CacheManager;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;


class SelectResolver
{
    
    private const ACTION    = 'action';
    
    private const COLUMN    = 'column';
    
    private const RELATION  = 'relation';
    
    private const TYPE      = 'type';
    
    private const NAME      = 'name';
    
    private const CLASSNAME = 'classname';
    
    /**
     * @Inject()
     */
    private Cache $cache;
    /**
     * @Inject()
     */
    private CacheManager $cacheManager;
    
    
    public function resolve( string $classname, $idOrSql, ?array $parameters, bool $lock )
    {
        $pregResult = preg_match( '/^[0-9]+$/', $idOrSql );
        
        if($pregResult){
            $object = $this->cacheManager->get($classname, $idOrSql, $lock);
            
            if(!empty($object)){
                return $object;
            }
        }elseif(empty($idOrSql)){
            $array = $this->cacheManager->getAll($classname, $lock);
            
            if(is_array($array)){
                return $array;
            }
        }
        
        $cache = $this->cache->getCache( $this->getShortName( $classname ), $classname, '__SELECT__' );
        $data  = $this->getData( $this->request( $this->getTable( $cache ), $idOrSql, $parameters, $lock ), $cache, $lock );
        
        if( $pregResult ) {
            return is_array($data) && !empty($data) ? $data[0] : NULL;
        }elseif(empty($idOrSql)){
            $this->cacheManager->addAll($classname);
        }
        
        
        return $data;
    }
    
    
    protected function getData( array $beans, array $cache, bool $lock = FALSE)
    {
        
        unset( $cache['nomess_table'] );
        
        
        if( empty( $beans ) || empty( $cache ) || !is_object( current( $beans ) ) || empty(current( $beans )->id) ) {
            
            return NULL;
        }
        
        $list = array();
        
        /** @var OODBBean $bean */
        foreach( $beans as $bean ) {
            
            $target = NULL;
            $insert = TRUE;
            
            if( array_key_exists( $cache['id'][self::CLASSNAME], Instance::$mapper ) ) {
                foreach( Instance::$mapper[$cache['id'][self::CLASSNAME]] as $array ) {
                    
                    if( $array['bean']->id === $bean->id ) {
                        $list[] = $array['object'];
                        $insert = FALSE;
                    }
                }
            }
            
            foreach( $cache as $columnName => $propertyData ) {
                $classname    = $propertyData[self::CLASSNAME];
                $propertyName = $propertyData[self::NAME];
                
                if( $target === NULL && $insert && $bean->id !== 0 ) {
                    
                    $cacheProvide = $this->cacheManager->get($classname, $bean->id, $lock);
                    
                    if($cacheProvide !== NULL){
                        $list[] = $cacheProvide;
                        $insert = FALSE;
                    }else {
                        $target = new $classname();
                        $this->subscribeToMapper( $target, $bean );
                        
                        $reflectionProperty = new \ReflectionProperty( $classname, $propertyName );
                        $reflectionProperty->setAccessible( TRUE );
                        $reflectionProperty->setValue( $target, $bean->id );
                        $this->cacheManager->add($target);
                    }
                }
                
                if( $propertyName !== 'id' && $insert ) {
                    $propertyColumn = $propertyData[self::COLUMN];
                    $purgeLazyLoad  = $bean->$propertyColumn;
                    
                    $propertyAction   = $propertyData[self::ACTION];
                    $propertyRelation = $propertyData[self::RELATION];
                    $propertyValue    = $bean->$propertyColumn;
                    
                    $reflectionProperty = new \ReflectionProperty( $classname, $propertyName );
                    
                    if( !$reflectionProperty->isPublic() ) {
                        $reflectionProperty->setAccessible( TRUE );
                    }
                    
                    if( $propertyAction === 'unserialize' ) {
                        $reflectionProperty->setValue( $target, !is_null( $propertyValue )  ? unserialize( $propertyValue ) : array() );
                    } elseif( $propertyAction === NULL ) {
                        $reflectionProperty->setValue( $target, $propertyValue );
                    } else {
                        
                        if( !empty( $propertyRelation ) ) {
                            if( $propertyValue !== NULL ) {
                                if( $propertyRelation['relation'] === 'ManyToOne' || $propertyRelation['relation'] === 'ManyToMany' ) {
                                    $tmp = array();
                                    
                                    foreach( $propertyValue as $value ) {
                                        $tmp[] = $this->getRelation( $propertyRelation, $value );
                                    }
                                    
                                    $reflectionProperty->setValue( $target, $tmp );
                                } elseif( $propertyRelation['relation'] === 'OneToMany' || $propertyRelation['relation'] === 'OneToOneOwner' ) {
                                    
                                    $reflectionProperty->setValue( $target, $this->getRelation( $propertyRelation, $propertyValue ) );
                                }
                                
                            }elseif($propertyRelation['relation'] !== 'OneToOne'){ // If OneToOne, propertyValue is normaly null
                                if($reflectionProperty->getType()->getName() === 'array'){
                                    $reflectionProperty->setValue($target, array());
                                }else{
                                    $reflectionProperty->setValue($target, NULL);
                                }
                            }
                        }
                        
                        // If propertyValue is null, set null unless if OneToOne relation (exception)
                        if( $propertyRelation['relation'] === 'OneToOne' ) {
                            $owner = $this->resolve( $propertyRelation['type'], $bean->getMeta( 'type' ) . '_id = :param', [ 'param' => $bean->id ], FALSE );
                            
                            if( !empty( $owner ) ) {
                                $reflectionProperty->setValue( $target, is_array($owner) ? $owner[0] : $owner );
                            }
                        }
                    }
                }
            }
            
            if( $insert === TRUE ) {
                $this->cacheManager->clonable($target);
                $list[] = $target;
            }
        }
        
        return $list;
    }
    
    
    private function getRelation( array $relation, $propertyValue ): ?object
    {
        if( !empty( $propertyValue ) ) {
            
            $classname = $relation['type'];
            
            if( !empty( Instance::$mapper ) && isset( Instance::$mapper[$classname] ) ) {
                foreach( Instance::$mapper[$classname] as $value ) {
                    if( $value['bean']->id === $propertyValue->id ) {
                        return $value['object'];
                    }
                }
            }
            
            return $this->getData( [ $propertyValue ], $this->cache->getCache( $this->getShortName( $classname ), $classname, '__SELECT__' ) )[0];
        }
        
        return NULL;
    }
    
    
    private function request( string $tableName, $idOrSql, ?array $parameters, bool $lock )
    {
        if( preg_match( '/^[0-9]+$/', $idOrSql ) ) {
            if( $lock === FALSE ) {
                return [ R::load( $tableName, $idOrSql ) ];
            }
            return [ R::loadForUpdate( $tableName, $idOrSql ) ];
        } elseif( is_string( $idOrSql ) ) {
            $data = NULL;
            
            if( $lock === FALSE ) {
                $data = R::find( $tableName, $idOrSql, ( !empty( $parameters ) ) ? $parameters : [] );
            } else {
                $data = R::findForUpdate( $tableName, $idOrSql, ( !empty( $parameters ) ) ? $parameters : [] );
            }
            
            return ( is_array( $data ) ) ? $data : [ $data ];
        } else {
            
            if( $lock === FALSE ) {
                return R::findAll( $tableName );
            }
            
            return R::findForUpdate( $tableName );
        }
    }
    
    
    private function subscribeToMapper( object $target, object $bean ): void
    {
        Instance::$mapper[get_class( $target )][] = [
            'object' => $target,
            'bean'   => $bean
        ];
    }
    
    
    private function getShortName( string $classname ): string
    {
        return substr( strrchr( $classname, '\\' ), 1 );
    }
    
    
    private function getTable( array &$data ): string
    {
        $table = $data['nomess_table'];
        unset( $data['nomess_table'] );
        
        return $table;
    }
}
