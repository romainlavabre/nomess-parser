<?php


namespace Nomess\Container;



class Container implements ContainerInterface
{

    private Autowire $autowire;

    public function __construct()
    {
        $this->autowire = new Autowire($this);
    }

    public function get(string $classname)
    {
        return $this->autowire->get($classname);
    }


    public function make(string $className)
    {
        return $this->autowire->make($className);
    }

    public function callController(string $classname, string $methodName)
    {
        $this->autowire->force['method'] = $methodName;
        $this->autowire->force['class'] = $classname;
        return $this->autowire->make($classname);
    }

}
