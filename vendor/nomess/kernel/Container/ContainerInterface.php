<?php


namespace Nomess\Container;


interface ContainerInterface
{
    public function get(string $classname);

    public function make(string $classname);
}
