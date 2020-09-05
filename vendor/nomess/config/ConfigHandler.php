<?php

namespace Nomess\Component\Config;

use Nomess\Component\Config\Exception\ConfigurationNotFoundException;

define( 'ROOT', str_replace( 'vendor/nomess/config', '', __DIR__ ) );

class ConfigHandler implements ConfigStoreInterface
{
    
    private const CONFIG_ROOT = ROOT . 'config/';
    private array  $config = array();
    private string $overwrite_extension;
    
    
    public function __construct()
    {
        $this->config[ConfigStoreInterface::DEFAULT_NOMESS] = $this->parse( ConfigStoreInterface::DEFAULT_NOMESS );
        $this->overwrite_extension                          = $this->config[ConfigStoreInterface::DEFAULT_NOMESS]['general']['overwrite_extension_config'];
    }
    
    
    /**
     * @inheritDoc
     */
    public function root(): string
    {
        return ROOT;
    }
    
    
    /**
     * @inheritDoc
     */
    public function get( string $name ): array
    {
        if( !array_key_exists( $name, $this->config ) ) {
            $this->config[$name] = $this->parse( $name );
        }
        
        return $this->config[$name];
    }
    
    
    /**
     * @inheritDoc
     */
    public function has( string $name ): bool
    {
        try {
            $this->get( $name );
        } catch( ConfigurationNotFoundException $e ) {
            return FALSE;
        }
        
        return TRUE;
    }
    
    
    /**
     * @param string $name
     * @return array
     * @throws ConfigurationNotFoundException
     */
    private function parse( string $name ): array
    {
        if( $name === ( ConfigStoreInterface::DEFAULT_NOMESS | ConfigStoreInterface::DEFAULT_CONTAINER | ConfigStoreInterface::DEFAULT_PARAMETER ) ) {
            if( ConfigStoreInterface::DEFAULT_NOMESS ) {
                
                return $this->parseFile( self::CONFIG_ROOT . ConfigStoreInterface::DEFAULT_NOMESS . '.yaml', FALSE );
            }
            
            return $this->parseFile( self::CONFIG_ROOT . $name . '.yaml' );
        }
        
        if( file_exists( $filename = self::CONFIG_ROOT . $this->config[ConfigStoreInterface::DEFAULT_NOMESS]['path']['default_config_component'] . $name . 'yaml' ) ) {
            return $this->parseFile( $filename );
        }
        
        throw new ConfigurationNotFoundException( 'The file "' . $name . '.yaml" was not found' );
    }
    
    
    /**
     * Return a parsed array
     *
     * @param string $filename
     * @param bool $accept_overwritten
     * @return array
     */
    private function parseFile( string $filename, bool $accept_overwritten = TRUE ): array
    {
        if( ROOT === 'DEV'
            && file_exists( str_replace( '.yaml', $this->overwrite_extension . '.yaml', $filename ) )
            && $accept_overwritten ) {
            
            return $this->parseArray(
                yaml_parse_file( str_replace( '.yaml', $this->overwrite_extension . '.yaml', $filename ) )
            );
        }
        
        return $this->parseArray(
            yaml_parse_file( $filename )
        );
    }
    
    
    /**
     * Replace variable of str
     *
     * @param array $array
     * @return array
     */
    private function parseArray( array $array ): array
    {
        array_walk_recursive( $array, function ( &$value ) {
            if( is_string( $value ) ) {
                if( strpos( $value, '%ROOT%' ) !== FALSE ) {
                    str_replace( '%ROOT%', ROOT, $value );
                }
            }
        } );
        
        return $array;
    }
}
