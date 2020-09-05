<?php


namespace Nomess\Components\ApplicationScope;


interface ApplicationScopeInterface
{
    
    public function has($index): bool;
    
    /**
     * Get data
     *
     * @param string $index
     * @return mixed|null
     */
    public function get(string $index);

    /**
     * Update data
     *
     * @param $key
     * @param $value
     * @param bool $reset
     */
    public function set($key, $value, $reset = false): void;
}
