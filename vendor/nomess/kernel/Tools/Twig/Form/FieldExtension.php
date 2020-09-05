<?php


namespace Nomess\Tools\Twig\Form;


use Nomess\Exception\MissingConfigurationException;
use Twig\TwigFunction;

class FieldExtension extends \Twig\Extension\AbstractExtension
{
    
    private bool           $bootstrap           = TRUE;
    private bool           $first               = TRUE;
    private ?string        $last_id             = NULL;
    private ?string        $last_label          = NULL;
    private ?string        $last_type           = NULL;
    private ValueExtension $value_extension;
    
    
    public function __construct( ValueExtension $value_extension )
    {
        $this->value_extension = $value_extension;
    }
    
    
    public function getFunctions()
    {
        return [
            new TwigFunction( 'input', [ $this, 'input' ] ),
            new TwigFunction( 'select', [ $this, 'select' ] ),
            new TwigFunction( 'textarea', [ $this, 'textarea' ] ),
            new TwigFunction( 'label', [ $this, 'label' ] ),
            new TwigFunction( 'bootstrap', [ $this, 'bootstrap' ] ),
            new TwigFunction( 'first', [ $this, 'first' ] )
        ];
    }
    
    
    public function bootstrap( bool $used )
    {
        $this->bootstrap = $used;
    }
    
    
    public function first( bool $first = TRUE ): void
    {
        $this->first = $first;
    }
    
    
    public function label( array $options = [] ): void
    {
        $this->last_label = $this->engineLabel( $options );
    }
    
    
    public function input( array $options = [], $valueExtension = NULL ): void
    {
        $this->show(
            $this->addBootstrap(
                $this->addLabel(
                    $this->engineInput( $options, $valueExtension )
                )
            )
        );
    }
    
    
    public function select( array $option_select = [], array $data = [], $valueExtension = NULL ): void
    {
        
        $this->show(
            $this->addBootstrap(
                $this->addLabel(
                    $this->engineSelect( $option_select, $data, $valueExtension )
                )
            )
        );
    }
    
    
    public function textarea( array $options = [], $valueExtension = NULL ): void
    {
        $this->show(
            $this->addBootstrap(
                $this->addLabel(
                    $this->engineTextarea( $options, $valueExtension )
                )
            )
        );
    }
    
    private function addLabel( string $field ): string
    {
        $label = NULL;
        
        if( !empty($this->last_label) ) {
            $label = str_replace( '<!--for-->', 'for="' . $this->last_id . '"', $this->last_label );
            
            if( $this->labelBefore() ) {
                return $label . $field;
            }
            
            return $field . $label;
        }
        
        return $field;
    }
    
    
    private function addBootstrap( string $content ): string
    {
        if( $this->bootstrap ) {
            $class = $this->last_type === 'checkbox' || $this->last_type === 'radio' ? 'form-check' : 'form-group';
            if( $this->first ) {
                return "<div class=\"$class\">" . $content . '</div>';
            } else {
                return "<div class=\"$class mt-5\">" . $content . '</div>';
            }
        }
        
        return $content;
    }
    
    private function labelBefore(): bool
    {
        $type = $this->last_type;
        
        return $type !== 'checkbox' && $type !== 'radio';
    }
    
    
    private function show( string $content ): void
    {
        echo $content;
        
        $this->last_type  = NULL;
        $this->last_id    = NULL;
        $this->last_label = NULL;
        $this->last_type  = NULL;
        $this->first      = FALSE;
    }
    
    
    private function engineInput( array $options, $valueExtension ): string
    {
        $metadata = $this->getMetadata($options, 'input');
        $valueExtension = $this->getValueExtension( $options, $valueExtension );
        
        return '<input ' . $this->getAttribute( $metadata, $options ) . ( is_array( $valueExtension ) ? 'value="' . call_user_func_array( [ $this->value_extension, 'value' ], $valueExtension ) . '"' : NULL ) . '>';
    }
    
    
    private function engineSelect( array $options, array $data, $valueExtension ): string
    {
        $metadata = $this->getMetadata($options, 'select');
        $valueExtension = $this->getValueExtension( $options, $valueExtension, TRUE );
        
        $content = '<select ' . $this->getAttribute( $metadata, $options ) . ">\n\t";
        
        foreach( $data as $key => $value ) {
            if( $key !== 'void' ) {
                if( is_array( $valueExtension ) ) {
                    $valueExtension[1] = $key;
                }
                $content .= "<option " . ( is_array( $valueExtension ) ? call_user_func_array( [ $this->value_extension, 'select' ], $valueExtension ) : NULL ) . " value=\"$key\">$value</option>\n";
            } else {
                $content .= "<option value=\"\">$value</option>\n";
            }
        }
        
        return $content . '</select>';
    }
    
    
    private function engineLabel( array $options ): string
    {
        $content = '<label ' . $this->getAttribute( [], $options, 'value' );
        
        if( array_key_exists( 'value', $options ) ) {
            $content .= '<!--for-->>' . $options['value'] . '</label>';
        } else {
            $content .= '></label>';
        }
        
        return $content;
    }
    
    
    private function engineTextarea( array $options, $valueExtension ): string
    {
        $metadata = $this->getMetadata($options, 'textarea');
        $valueExtension = $this->getValueExtension( $options, $valueExtension );
        
        return '<textarea ' . $this->getAttribute( $metadata, $options, 'value' ) . '>' . ( isset( $metadata['value'] ) ? $metadata['value'] : ( is_array( $valueExtension ) ? call_user_func_array( [ $this->value_extension, 'value' ], $valueExtension ) : NULL ) ) . '</textarea>';
    }
    
    private function getClassFor( ?string $type, array $options ): ?string
    {
        if( $this->bootstrap ) {
            
            if( isset( $options['type'] ) ) {
                $type            = $type . '::' . $options['type'];
                $this->last_type = $options['type'];
            }
            
            if( $type === 'input::file' ) {
                return 'form-control-file';
            }
            
            if( $type === 'input::checkbox' || $type === 'input::radio' ) {
                return 'form-check-input';
            }
            
            if( $type === 'label' ) {
                if( $this->last_type === 'checkbox' || $this->last_type === 'radio' ) {
                    return 'form-check-label';
                }
                
                return NULL;
            }
            
            return 'form-control';
        }
        
        return null;
    }
    
    
    private function getAttribute( array $metadata, array $options, ?string $exclude_key = NULL ): ?string
    {
        
        $content  = NULL;
        $metadata = array_merge( $metadata, $options );
        
        foreach( $metadata as $attribute => $value ) {
            if( !is_null( $value ) && $value !== FALSE && $attribute !== $exclude_key ) {
                $content .= "$attribute=\"$value\" ";
            } elseif( $value === TRUE ) {
                $content .= "$attribute ";
            }
        }
        
        return $content;
    }
    
    
    private function buildId( array $options ): ?string
    {
        if( array_key_exists( 'name', $options ) ) {
            return $this->last_id = 'form_' . str_replace('[]', '', $options['name']);
        }
        
        return NULL;
    }
    
    
    private function getValueExtension( array $options, $valueExtension, bool $select = FALSE )
    {
        if( !isset( $options['value'] ) ) {
            if(!isset($options['name'])){
                throw new MissingConfigurationException('A select miss a name property, please, add name property or disable the value extension');
            }
            
            if( is_null( $valueExtension ) ) {
                $valueExtension = [
                    0 => $options['name'],
                ];
            } elseif( is_array( $valueExtension ) ) {
                $tmp = [
                    0 => $options['name'],
                ];
                
                if( $select ) {
                    $tmp[2] = NULL;
                }
                
                $valueExtension = array_merge( $tmp, $valueExtension );
            }
        } else {
            $valueExtension = FALSE;
        }
        
        return $valueExtension;
    }
    
    private function getMetadata(array $options, string $type): array
    {
        return [
            'required' => TRUE,
            'class' => $this->getClassFor($type, $options),
            'id' => $this->buildId($options),
            'type' => $type === 'input' ? 'text' : NULL
        ];
    }
}
