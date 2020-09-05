<?php


namespace Nomess\Components\LightPersists;


use Nomess\Exception\NomessException;

interface LightPersistsInterface
{

    public function has($index): bool;
    
    public function &getReference($index);

    /**
     * Add value in container
     *
     * @param mixed $key
     * @param mixed $value
     * @param bool $reset Delete value associate to index before instertion
     * @return void
     */
    public function set($key, $value, $reset = false): void;

    /**
     * Return value associate to index variable or null if doesn't exists
     *
     * @param mixed $index
     * @return mixed
     */
    public function get($index);

    /**
     * Delete an pair key/value
     *
     * @param string $index
     * @return void
     * @throws NomessException
     */
    public function delete(string $index);

    /**
     * Delete the persistence file
     */
    public function purge(): void;
}
