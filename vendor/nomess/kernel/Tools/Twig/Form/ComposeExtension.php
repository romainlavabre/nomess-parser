<?php


namespace Nomess\Tools\Twig\Form;


use Twig\TwigFunction;

class ComposeExtension extends \Twig\Extension\AbstractExtension
{
    private array          $reflection_property = array();
    
    public function getFunctions()
    {
        return [
            new TwigFunction( 'compose', [ $this, 'compose' ] )
        ];
    }
    
    public function compose( $objects, array $toCompose, ?array $search = NULL ): array
    {
        if( is_object( $objects ) ) {
            $objects = [ $objects ];
        }
        
        $key      = key( $toCompose );
        $value    = current( $toCompose );
        $composed = array();
        $data     = NULL;
        
        if( !empty( $objects ) ) {
            
            if( !empty( $search ) ) {
                $data = $this->takeDataForCompose( $objects, $search );
            } else {
                $data = $objects;
            }
            
            $array_key   = array();
            $array_value = array();
            
            preg_match_all( '/prop\((.+)\)/', $key, $key_output );
            
            if( isset( $key_output[1] ) ) {
                $iteration = count( $key_output[1] );
                
                for( $i = 0; $i < $iteration; $i++ ) {
                    $array_key["prop(" . $key_output[1][$i] . ")"] = $key_output[1][$i];
                }
            }
            
            preg_match_all( '/prop\(([a-zA-Z0-9_-]+)\)/', $value, $value_output );
            if( isset( $value_output[1] ) ) {
                $iteration = count( $value_output[1] );
                
                for( $i = 0; $i < $iteration; $i++ ) {
                    $array_value["prop(" . $value_output[1][$i] . ")"] = $value_output[1][$i];
                }
            }
            
            foreach( $data as $object ) {
                $composed_key   = $key;
                $composed_value = $value;
                $classname      = get_class( $object );
                
                foreach( $array_key as $toReplace => $propertyName ) {
                    $reflectionProperty = $this->getReflectionProperty( $classname, $propertyName );
                    
                    $composed_key = str_replace( $toReplace, $reflectionProperty->getValue( $object ), $composed_key );
                }
                
                foreach( $array_value as $toReplace => $propertyName ) {
                    $reflectionProperty = $this->getReflectionProperty( $classname, $propertyName );
                    
                    $composed_value = str_replace( $toReplace, $reflectionProperty->getValue( $object ), $composed_value );
                }
                
                $composed["$composed_key"] = $composed_value;
            }
        }
        
        return $composed;
    }
    
    
    private function takeDataForCompose( $contains, array $search ): array
    {
        $data = array();
        
        if( is_array( $contains ) ) {
            foreach( $contains as $value ) {
                if( is_array( $value ) ) {
                    $data = array_merge( $data, $this->takeDataForCompose( $value, $search ) );
                } elseif( is_object( $value ) ) {
                    $classname = get_class( $value );
                    
                    if( array_key_exists( $classname, $search ) ) {
                        $reflectionProperty = $this->getReflectionProperty( $classname, $search[$classname] );
                        
                        if( $reflectionProperty->isInitialized( $value ) ) {
                            $data = array_merge( $data, $this->takeDataForCompose( $reflectionProperty->getValue( $value ), $search ) );
                        }
                    } else {
                        $data[] = $value;
                    }
                }
            }
        } elseif( is_object( $contains ) ) {
            
            $classname = get_class( $contains );
            
            if( array_key_exists( $classname, $search ) ) {
                $reflectionProperty = $this->getReflectionProperty( $classname, $search[$classname] );
                
                if( $reflectionProperty->isInitialized( $contains ) ) {
                    $data = array_merge( $data, $this->takeDataForCompose( $reflectionProperty->getValue( $contains ), $search ) );
                }
            } else {
                $data[] = $contains;
            }
        }
        
        return $data;
    }
    
    private function getReflectionProperty( string $classname, string $propertyName ): \ReflectionProperty
    {
        if( isset( $this->reflection_property[$classname][$propertyName] ) ) {
            return $this->reflection_property[$classname][$propertyName];
        }
        
        $reflectionProperty = new \ReflectionProperty( $classname, $propertyName );
        
        if( !$reflectionProperty->isPublic() ) {
            $reflectionProperty->setAccessible( TRUE );
        }
        
        return $this->reflection_property[$classname][$propertyName] = $reflectionProperty;
    }
}
