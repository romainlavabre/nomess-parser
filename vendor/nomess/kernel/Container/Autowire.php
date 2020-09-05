<?php


namespace Nomess\Container;


use Nomess\Exception\MissingConfigurationException;
use Nomess\Exception\NotFoundException;
use ReflectionMethod;

class Autowire
{
    private const CONFIGURATION         = ROOT . 'config/container.php';

    private array $instance = array();
    private array $configuration;
    public ?array $force = array();

    public function __construct(Container $container)
    {
        $this->instance[Container::class] = $container;
        $this->configuration = require self::CONFIGURATION;
    }

    public function get(string $classname)
    {
        if(array_key_exists($classname, $this->instance)){
            return $this->instance[$classname];
        }else{
            return $this->make($classname);
        }
    }

    /**
     * @param string $classname
     * @return mixed
     * @throws MissingConfigurationException
     * @throws \ReflectionException
     */
    public function make(string $classname)
    {
        $reflectionClass = new \ReflectionClass($classname);

        if($reflectionClass->getConstructor() !== NULL) {
            $this->constructorResolver($reflectionClass->getConstructor()->getParameters(), $reflectionClass);
        }else{
            $this->constructorResolver(NULL, $reflectionClass);
        }
    
        $this->propertyResolver($reflectionClass->getProperties(), $this->instance[$classname]);
        $this->methodsResolver($reflectionClass->getMethods(), $this->instance[$classname]);
    
        return $this->instance[$classname];

    }


    /**
     * @param \ReflectionParameter[]|null $reflectionParameters
     * @param \ReflectionClass $reflectionClass
     * @return void
     */
    private function constructorResolver(?array $reflectionParameters, \ReflectionClass $reflectionClass): void
    {
        $parameters = array();

        if(!empty($reflectionParameters)){
            foreach($reflectionParameters as $reflectionParameter){
                $parameters[] = $this->getGroup($reflectionParameter->getType()->getName(), $reflectionParameter->getName(), $reflectionClass->getConstructor());
            }
        }

        $this->instance[$reflectionClass->getName()] = $reflectionClass->newInstanceArgs($parameters);
    }

    /**
     * @param ReflectionMethod[]|null $reflectionMethods
     * @param object $object
     */
    private function methodsResolver(?array $reflectionMethods, object $object): void
    {
        foreach($reflectionMethods as $reflectionMethod){
            
            if($this->hasAnnotation($reflectionMethod)
                || (!empty($this->force)
                    && ($reflectionMethod->getName() === $this->force['method']
                    && $reflectionMethod->getDeclaringClass()->getName() === $this->force['class']))){
                $this->purgeForce($reflectionMethod);
                $parameters = array();

                $reflectionParameters = $reflectionMethod->getParameters();

                if(!empty($reflectionParameters)){
                    foreach($reflectionParameters as $reflectionParameter){
                        $parameters[] = $this->getGroup($reflectionParameter->getType()->getName(), $reflectionParameter->getName(), $reflectionMethod);
                    }
                }

                $reflectionMethod->invokeArgs($object, $parameters);
            }
        }
    }

    /**
     * @param \ReflectionProperty[]|null $reflectionProperties
     * @param object $object
     * @throws MissingConfigurationException
     */
    private function propertyResolver(?array $reflectionProperties, object $object): void
    {
        if(!empty($reflectionProperties)){
            foreach($reflectionProperties as $reflectionProperty){
                if($this->hasAnnotation($reflectionProperty)){

                    if(!$reflectionProperty->isPublic()) {
                        $reflectionProperty->setAccessible(TRUE);
                    }

                    $reflectionProperty->setValue(
                        $object,
                        $this->getGroup($reflectionProperty->getType()->getName(), $reflectionProperty->getName(), $reflectionProperty)
                    );
                }
            }
        }
    }

    private function getGroup(string $type, string $paramName, $reflection)
    {
        $list = array();

        if($type === 'array'){

            if($reflection instanceof ReflectionMethod) {
                $type = $this->getTypeAnnotationParameter($paramName, $reflection);
            }else{
                $type = $this->getTypeAnnotationProperty($paramName, $reflection);
            }

        }

        if(isset($this->configuration[$type])){

            if(is_array($this->configuration[$type])){
                if(array_key_exists($paramName, $this->configuration[$type])){
                    return $this->getInstance($this->configuration[$type][$paramName]);
                }else{ // If is not array, send and array of parameter
                    foreach($this->configuration[$type] as $class){
                        $list[] = $this->getInstance($class);
                    }
                }
            }else{
                return $this->getInstance($this->configuration[$type]);
            }
        }else{
            return $this->getInstance($type);
        }

        return $list;
    }

    private function getInstance(string $type)
    {
        $reflectionClass = new \ReflectionClass($type);

        if(array_key_exists($type, $this->instance)){
            return $this->instance[$type];
        }

        if($reflectionClass->isInstantiable()){

            return $this->make($type);

        }else{
            throw new MissingConfigurationException("Impossible of autowire the class $type, she's not instanciable");
        }
    }

    private function hasAnnotation(\Reflector $reflector): bool
    {
        if(strpos($reflector->getDocComment(), '@Inject') !== FALSE){
            return TRUE;
        }else{
            return FALSE;
        }
    }

    private function getTypeAnnotationParameter(string $paramName, ReflectionMethod $reflectionMethod): string
    {
        preg_match('/@param ([A-Za-z0-1_\\\]+)\[?\]?[|null]* \$' . $paramName . '/', $reflectionMethod->getDocComment(), $output);

        if(isset($output[1])){
            if(class_exists($output[1])){
                return $output[1];
            }else{
                return $this->criticalClassResolver($output[1], $reflectionMethod->getDeclaringClass());
            }
        }else{
            throw new \InvalidArgumentException("The argument $$paramName in " . $reflectionMethod->getDeclaringClass() . ' for method ' . $reflectionMethod->getName() . ' has unresolved');
        }
    }

    private function getTypeAnnotationProperty(string $paramName, \ReflectionProperty $reflectionProperty): string
    {
        preg_match('/@var ([A-Za-z0-1_\\\]+)\[?\]?[|null]*/', $reflectionProperty->getDocComment(), $output);

        if(isset($output[1])){
            if(class_exists($output[1])){
                return $output[1];
            }else{
                return $this->criticalClassResolver($output[1], $reflectionProperty->getDeclaringClass());
            }
        }else{
            throw new \InvalidArgumentException("The argument $$paramName in " . $reflectionProperty->getDeclaringClass()->getName() . ' has unresolved');
        }
    }

    private function criticalClassResolver(string $classname, \ReflectionClass $reflectionClass): ?string
    {
        //Search for class in used namespace
        $file = file($reflectionClass->getFileName());
        $found = array();


        foreach($file as $line) {
            if(strpos($line, $classname) !== FALSE && strpos($line, 'use') !== FALSE){

                preg_match('/ +[A-Za-z0-9_\\\]*/', $line, $output);
                $found[] = trim($output[0]);
            }
        }

        // If not found, trying with namespace of original class or if she's declared in configuration
        if(empty($found)){
            if(class_exists($reflectionClass->getNamespaceName() . '\\' . $classname)) {
                return $reflectionClass->getNamespaceName() . '\\' . $classname;
            }elseif(isset($this->configuration[$classname])){
                return $classname;
            }else {
                throw new NotFoundException('Autowiring encountered an error: class ' . $classname . ' cannot be resolved, mentioned in ' . $reflectionClass->getName());
            }
        }elseif(count($found) === 1){
            return $found[0];
        }else{
            // If it has been found several times, search in configuration, if all definition match, class is unresolved
            $defined = array();

            foreach($found as $fullname){
                if(isset($this->configuration[$fullname])){
                    $defined[] = $fullname;
                }
            }

            if(empty($defined) || count($defined) > 1){
                throw new NotFoundException('Autowiring encountered an error: impossible of resolved the class ' . $classname . ' mentionned in ' . $reflectionClass->getName());
            }else{
                return $defined[0];
            }
        }
    }

    private function purgeForce(ReflectionMethod $reflectionMethod): void
    {
        if($this->force['method'] === $reflectionMethod->getName()
           && $this->force['class'] === $reflectionMethod->getDeclaringClass()->getName()) {
            
            $this->force['method'] = NULL;
            $this->force['class'] = NULL;
        }
    }
}
