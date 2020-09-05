<?php


namespace Nomess\Tools\Twig\Form;


use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ValueExtension extends AbstractExtension
{
    
    private                   $data            = NULL;
    private ?\ReflectionClass $reflectionClass = NULL;
    private ?object           $instance        = NULL;
    
    
    public function __construct( ?array $post )
    {
        $this->data = $post;
    }
    
    
    public function getFunctions()
    {
        return [
            new TwigFunction( 'form_bind', [ $this, 'bind' ] ),
            new TwigFunction( 'form_value', [ $this, 'value' ] ),
            new TwigFunction( 'form_select', [ $this, 'select' ] ),
            new TwigFunction( 'form_close', [ $this, 'close' ] )
        ];
    }
    
    
    public function close(): void
    {
        $this->reflectionClass = NULL;
        $this->instance        = NULL;
    }
    
    
    public function bind( ?object $instance )
    {
        if( $instance !== NULL ) {
            $this->reflectionClass = new \ReflectionClass( $instance );
            $this->instance        = $instance;
        }
    }
    
    
    public function value( string $name, ?string $propertyName = NULL, string $default = NULL ): ?string
    {
        $name = $this->purgeName( $name );
        
        if( isset( $this->data[$name] ) ) {
            return $this->data[$name];
        }
        
        if( $this->reflectionClass !== NULL ) {
            
            $reflectionProperty = NULL;
            
            try {
                if( $propertyName != NULL ) {
                    $reflectionProperty = $this->reflectionClass->getProperty( $propertyName );
                } else {
                    $reflectionProperty = $this->reflectionClass->getProperty( $name );
                }
            } catch( \Throwable $e ) {
            }
            
            if( $reflectionProperty !== NULL ) {
                if( !$reflectionProperty->isPublic() ) {
                    $reflectionProperty->setAccessible( TRUE );
                }
                
                if( !is_object( $reflectionProperty->getValue( $this->instance ) ) ) {
                    return $reflectionProperty->getValue( $this->instance );
                }
            }
        }
        
        if( !empty( $default ) ) {
            return $default;
        }
        
        return NULL;
    }
    
    
    public function select( string $name, string $value, ?array $searchData = NULL, ?string $propertyName = NULL ): ?string
    {
        $name = $this->purgeName( $name );
        
        if( isset( $this->data[$name] ) ) {
            if( is_array( $this->data[$name] ) ) {
                foreach( $this->data[$name] as $data ) {
                    if( $data === $value ) {
                        return 'selected';
                    }
                }
            } elseif( (string)$this->data[$name] === (string)$value ) {
                return 'selected';
            }
        } elseif( $searchData === NULL ) {
            if( $this->reflectionClass !== NULL ) {
                
                $reflectionProperty = NULL;
                
                try {
                    if( $propertyName != NULL ) {
                        $reflectionProperty = $this->reflectionClass->getProperty( $propertyName );
                    } else {
                        $reflectionProperty = $this->reflectionClass->getProperty( $name );
                    }
                } catch( \Throwable $e ) {
                }
                
                if( $reflectionProperty !== NULL ) {
                    if( !$reflectionProperty->isPublic() ) {
                        $reflectionProperty->setAccessible( TRUE );
                    }
                    
                    $valueProperty = $reflectionProperty->getValue( $this->instance );
                    
                    if( is_array( $valueProperty ) ) {
                        foreach( $valueProperty as $data ) {
                            if( is_object( $data ) ) {
                                if( (string)$data->getId() === (string)$value ) {
                                    return 'selected';
                                }
                            } else {
                                if( (string)$data === (string)$value ) {
                                    return 'selected';
                                }
                            }
                        }
                    } else {
                        try {
                            if( ( is_object( $valueProperty ) && (string)$valueProperty->getId() === (string)$value )
                                || (string)$valueProperty === (string)$value ) {
                                
                                return 'selected';
                            }
                        } catch( \Throwable $e ) {
                        }
                    }
                }
            }
        } else {
            foreach( $searchData as $data ) {
                if( $value === $data ) {
                    return 'selected';
                }
            }
        }
        
        return NULL;
    }
    
    
    private function purgeName( string $name ): string
    {
        if( strpos( $name, '[' ) !== FALSE ) {
            return explode( '[', $name )[0];
        }
        
        return $name;
    }
}
