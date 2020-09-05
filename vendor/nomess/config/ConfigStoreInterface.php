<?php

namespace Nomess\Component\Config;

use Nomess\Component\Config\Exception\ConfigurationNotFoundException;

interface ConfigStoreInterface
{
    
    public const DEFAULT_NOMESS    = 'nomess';
    public const DEFAULT_CONTAINER = 'container';
    public const DEFAULT_PARAMETER = 'parameter';
    
    
    /**
     * Return root directory of nomess
     *
     * @return string
     */
    public function root(): string;
    
    
    /**
     * Return an array with a configuration
     *
     * @param string $name
     * @return array|null
     * @throws ConfigurationNotFoundException
     */
    public function get( string $name ): array;
    
    
    /**
     * Return bool if an configuration exists for this name
     *
     * @param string $name
     * @return bool
     */
    public function has( string $name ): bool;
}
