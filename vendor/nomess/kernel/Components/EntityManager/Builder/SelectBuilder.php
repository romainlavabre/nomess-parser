<?php

namespace Nomess\Components\EntityManager\Builder;

use Nomess\Exception\ORMException;

class SelectBuilder extends AbstractBuilder
{


    public function builder(string $classname): array
    {
        $reflectionClass = new \ReflectionClass($classname);

        $properties = $this->propertiesResolver($reflectionClass->getProperties());
        $properties['nomess_table'] = $this->tableResolver($this->getShortenName($classname));

        return $properties;
    }


    /**
     * Precise the action to executed
     *
     * @param \ReflectionProperty $reflectionProperty
     * @return string|null
     * @throws ORMException
     */
    protected function getAction(\ReflectionProperty $reflectionProperty): ?string
    {
        $type = $this->getType($reflectionProperty);


        if($type === 'array'){
            return 'unserialize';
        }elseif(class_exists($type)
            && $reflectionProperty->getType()->getName() === 'array'){

            return 'iteration';

        }elseif(class_exists($type)){
            return 'bean';
        }

        return NULL;

    }
}
