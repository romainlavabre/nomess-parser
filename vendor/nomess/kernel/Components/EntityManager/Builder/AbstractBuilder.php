<?php


namespace Nomess\Components\EntityManager\Builder;


use Nomess\Exception\MissingConfigurationException;
use Nomess\Exception\ORMException;

abstract class AbstractBuilder
{
    
    protected function columnResolver( string $propertyName, string $classname, ?array $relation = NULL ): string
    {
        
        // If short name is empty, the value is type of php class
        if( !empty( $relation ) ) {
            if( $relation['relation'] === 'ManyToOne' ) {
                return 'own' . ucfirst( $this->tableResolver( $this->getShortenName( $relation['type'] ) ) ) . 'List';
            } elseif( $relation['relation'] === 'ManyToMany' ) {
                return 'shared' . ucfirst( $this->tableResolver( $this->getShortenName( $relation['type'] ) ) ) . 'List';
            } elseif( $relation['relation'] === 'OneToMany' || $relation['relation'] === 'OneToOneOwner' || $relation['relation'] === 'OneToOne' ) {
                return $this->tableResolver( $this->getShortenName( $relation['type'] ) );
            }
        }
        
        if( preg_match( '/[A-Za-z0-9_]+/', $propertyName ) ) {
            if( !preg_match( '/[A-Za-z0-9_]+_id/', $propertyName ) ) {
                return $propertyName;
            }
        }
        
        throw new ORMException( "ORM encountered an error: your property $$propertyName in class " . $classname .
                                ' isn\'t compatible with redbean, Accepted:<br><br> A-Za-z0-9_<br>Property finishing by "_id" in exclude' );
    }
    
    
    protected function tableResolver( string $classname ): string
    {
        $classname = preg_replace( '/0-9_/', '', $classname );
        
        return mb_strtolower( $classname );
    }
    
    
    /**
     * @param \ReflectionProperty $reflectionProperty
     * @return string|null
     */
    protected function searchType( \ReflectionProperty $reflectionProperty ): ?string
    {
        preg_match( '/@var ([A-Za-z0-1_\\\]+)\[?\]?[|null]*/', $reflectionProperty->getDocComment(), $output );
        
        if( !empty( $output ) ) {
            return $output[1];
        }
        
        return NULL;
    }
    
    
    protected function getShortenName( string $classname ): string
    {
        return substr( strrchr( $classname, '\\' ), 1 );
    }
    
    
    protected function criticalClassResolver( string $classname, \ReflectionClass $reflectionClass ): ?string
    {
        //Search for class in used namespace
        $file  = file( $reflectionClass->getFileName() );
        $found = array();
        
        
        foreach( $file as $line ) {
            
            if( strpos( $line, $classname ) !== FALSE && strpos( $line, 'use' ) !== FALSE ) {
                
                preg_match( '/ +[A-Za-z0-9_\\\]*/', $line, $output );
                $found[] = trim( $output[0] );
            }
        }
        if( empty( $found ) ) {
            if( class_exists( $reflectionClass->getNamespaceName() . '\\' . $classname ) ) {
                return $reflectionClass->getNamespaceName() . '\\' . $classname;
            }
        } elseif( count( $found ) === 1 ) {
            return $found[0];
        }
        throw new ORMException( 'ORM encountered an error: impossible to resolving the type ' . $classname . ' in @var annotation in ' . $reflectionClass->getName() );
    }
    
    
    /**
     * Search the type of property (real type)
     *
     * @param \ReflectionProperty $reflectionProperty
     * @return string
     * @throws ORMException
     */
    protected function getType( \ReflectionProperty $reflectionProperty ): string
    {
        if( $reflectionProperty->getType() !== NULL ) {
            $type = $reflectionProperty->getType()->getName();
            
            // If type is array, can be an array of relations or an arbitrary array
            if( $type === 'array' ) {
                $arrayContentType = $this->searchType( $reflectionProperty );
                
                if( $arrayContentType !== NULL ) {
                    return $this->criticalClassResolver( $arrayContentType, $reflectionProperty->getDeclaringClass() );
                }
            }
            
            if( ( strpos( $reflectionProperty->getDocComment(), '@ManyTo' ) !== FALSE
                  || strpos( $reflectionProperty->getDocComment(), '@OneTo' ) !== FALSE ) && !class_exists( $type ) ) {
                
                throw new MissingConfigurationException( 'ORM encountered an error: the property ' . $reflectionProperty->getName() .
                                                         ' of class ' . $reflectionProperty->getDeclaringClass()->getName() .
                                                         ' is an relation but the type is unknow' );
            }
            
            return $type;
        }
        
        throw new ORMException( 'ORM encountered an error: property ' . $reflectionProperty->getName() .
                                ' in class ' . $reflectionProperty->getDeclaringClass()->getName() . ' has not type' );
    }
    
    
    /**
     * Search the relation
     *
     * @param \ReflectionProperty $reflectionProperty
     * @return array|null
     * @throws ORMException
     */
    protected function relationResolver( \ReflectionProperty $reflectionProperty ): ?array
    {
        
        $type = $this->getType( $reflectionProperty );
        
        if( class_exists( $type ) ) {
            $comment = $reflectionProperty->getDocComment();
            
            if( strpos( $comment, '@ManyToMany' ) !== FALSE ) {
                return [
                    'relation' => 'ManyToMany',
                    'type'     => $type
                ];
            } elseif( strpos( $comment, '@ManyToOne' ) !== FALSE ) {
                return [
                    'relation' => 'ManyToOne',
                    'type'     => $type
                ];
            } elseif( strpos( $comment, '@OneToOne' ) !== FALSE ) {
                
                if( strpos( $comment, '@Owner' ) !== FALSE ) {
                    return [
                        'relation' => 'OneToOneOwner',
                        'type'     => $type
                    ];
                } else {
                    return [
                        'relation'     => 'OneToOne',
                        'type'         => $type,
                        'propertyName' => $this->mappedBy( $reflectionProperty )
                    ];
                }
            } elseif( strpos( $comment, '@OneToMany' ) !== FALSE ) {
                return [
                    'relation' => 'OneToMany',
                    'type'     => $type
                ];
            }
            
            throw new ORMException( 'ORM encountered an error: the relation for property ' . $reflectionProperty->getName() .
                                    ' in ' . $reflectionProperty->getDeclaringClass()->getName() . ' is undefined' );
        }
        
        return NULL;
    }
    
    
    /**
     * Build property for cache
     *
     * @param \ReflectionProperty[]|null $reflectionProperties
     * @return array
     * @throws ORMException
     */
    protected function propertiesResolver( ?array $reflectionProperties ): array
    {
        $list = array();
        
        if( !empty( $reflectionProperties ) ) {
            
            $declaringClass = $reflectionProperties[0]->getDeclaringClass()->getName();
            
            foreach( $reflectionProperties as $reflectionProperty ) {
                
                if( strpos( $reflectionProperty->getDocComment(), '@Stateless' ) === FALSE ) {
                    $propertyType = $this->getType( $reflectionProperty );
                    $propertyName = $reflectionProperty->getName();
                    $relation     = $this->relationResolver( $reflectionProperty );
                    $columnName   = $this->columnResolver(
                        $propertyName,
                        $declaringClass,
                        $relation
                    );
                    
                    $list[$columnName] = [
                        'action'    => $this->getAction( $reflectionProperty ),
                        'column'    => $columnName,
                        'name'      => $propertyName,
                        'relation'  => $relation,
                        'type'      => $propertyType,
                        'classname' => $reflectionProperty->getDeclaringClass()->getName()
                    ];
                }
            }
        }
        
        return $list;
    }
    
    
    private function mappedBy( \ReflectionProperty $reflectionProperty ): string
    {
        $comment = $reflectionProperty->getDocComment();
        
        preg_match( '/@MappedBy\("(.+)"\)/', $comment, $output );
        
        if( !empty( $output[1] ) ) {
            return $output[1];
        } else {
            throw new ORMException( 'Your property ' . $reflectionProperty->getName() . ' of class ' .
                                    $reflectionProperty->getDeclaringClass()->getName() . ' must specified an @MappedBy annotation' );
        }
    }
}
