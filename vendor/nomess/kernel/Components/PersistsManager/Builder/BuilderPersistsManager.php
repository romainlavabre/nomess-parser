<?php


namespace Nomess\Components\PersistsManager\Builder;


use \Container\Container;
use Nomess\Exception\NotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;


class BuilderPersistsManager
{

    private const CACHE_PATH            = ROOT . 'App/var/cache/pm/persistsmanager.php';

    private static array $comment = [
        'table' => '@PM\\Table',
        'column' => '@PM\\Column',
        'dependency' => '@PM\\Dependency',
        'keyArray' => '@PM\\KeyArray',
        'patch' => '@PM\\Patch',
        'extends' => '@PM\\Extends'
    ];

    private array $extends = array();

    private string $className;

    private string $keyArray;



    /**
     * Contains table associate to this object
     */
    private string $table;

    /**
     * Contains a property data in this format:
     * array[] = [
     *      column
     *      accessor
     *      mutator
     *      type
     *      scope
     *      table
     *      keyArray
     * ]
     */
    private array $property;


    /**
     * Contains dependency of this class in this format
     * array[dependency class name] = [
     *      scope
     *      method
     * ]
     */
    private array $dependency;



    private Container $container;


    /**
     * @Inject
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Builder
     *
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function build(string $classname) : void
    {
        $this->className = $classname;
        $reflectionClass = new ReflectionClass($classname);

        $this->getCommentClass($reflectionClass);
        $this->getKeyArray($reflectionClass->getProperties(), $reflectionClass);
        $this->getCommentProperty($reflectionClass->getProperties(), $reflectionClass);
        $this->getDependency($reflectionClass->getProperties(), $reflectionClass);
        $this->pushCacheData();


    }


    /**
     * Get comment of the class if exists
     *
     * @param ReflectionClass $reflectionClass
     * @throws NotFoundException
     */
    private function getCommentClass(ReflectionClass $reflectionClass): void
    {
        $commentClass = $reflectionClass->getDocComment();

        if(strpos($commentClass, self::$comment['table']) !== false){
            preg_match('/@PM\\\Table\([a-zA-Z0-9-_&\/\\\~@#]+\)/', $commentClass, $outputTable);

            if(!empty($outputTable[0])){
                $this->table = str_replace(['@PM\\Table(', ')'], '', $outputTable[0]);
            }else{
                throw new NotFoundException('BuilderPersistsManager encountered an error: table name could not be resolved for ' . $reflectionClass->getName() . ', but exists, please, verify your syntax');
            }

            if(strpos($commentClass, self::$comment['extends']) !== false){
                preg_match_all('/@PM\\\Extends\([a-zA-Z0-9-_&\/\~@#]+,[a-zA-Z0-9-_&\/\~@#]+\)/', $commentClass, $outputExtends);

                if(!empty($outputExtends)) {
                    foreach ($outputExtends[0] as $value) {

                        $value = str_replace(['@PM\\Extends(', ')'], '', $value);

                        $result = explode(',', $value);

                        $this->extends[$result[0]] = $result[1];

                    }
                }
            }
        }else {

            throw new NotFoundException('BuilderPersistsManager encountered an error: table name could not be resolved for ' . $reflectionClass->getName());
        }
    }


    /**
     * Create properties configuration
     *
     * @param array $properties
     * @param ReflectionClass $reflectionClass
     * @throws ReflectionException
     * @throws NotFoundException
     */
    private function getCommentProperty(array $properties, ReflectionClass $reflectionClass): void
    {
        foreach ($properties as $value){
            $reflectionProperty = new ReflectionProperty($reflectionClass->getName(), $value->getName());


            $column = $this->getColumnProperty($reflectionProperty, $reflectionClass);


            if($column !== null){
                $type = $this->getTypeProperty($reflectionProperty, $reflectionClass);
                $scope = $this->getScopeProperty($reflectionProperty);
                $accessor = $this->getAccessorProperty($reflectionClass, $reflectionProperty, $scope);
                $mutator = $this->getMutatorProperty($reflectionClass, $reflectionProperty, $scope);
                $table = $this->table;
                $keyArray = $this->keyArray;

                $this->property[] = [
                    'column' => $column,
                    'accessor' => $accessor,
                    'mutator' => $mutator,
                    'type' => $type,
                    'scope' => $scope,
                    'table' => $table,
                    'keyArray' => $keyArray
                ];
            }
        }
    }


    /**
     * Take the dependency of this class
     *
     * @param array $properties
     * @param ReflectionClass $reflectionClass
     * @throws ReflectionException
     * @throws NotFoundException
     */
    private function getDependency(array $properties, ReflectionClass $reflectionClass): void
    {
        foreach ($properties as $value){
            $reflectionProperty = $value;

            if(strpos($reflectionProperty->getDocComment(), self::$comment['dependency'])){

                $type = $this->getTypeDependency($reflectionProperty, $reflectionClass);

                $this->dependency[$type]['scope'] = $this->getScopeProperty($reflectionProperty);

                if($this->dependency[$type]['scope'] === 'public'){
                    $this->dependency[$type]['mutator'] = $reflectionProperty->getName();
                }else{
                    try {
                        $this->dependency[$type]['mutator'] = $reflectionClass->getMethod('set' . ucfirst($reflectionProperty->getName()))->getName();
                    } catch (ReflectionException $rf) {

                        $accessor = $this->searchPatch($reflectionProperty->getName(), $reflectionClass, 'Mutator');

                        if ($accessor === null) {
                            throw new NotFoundException('BuilderPersistsManager encountered an error: accessor for property ' .
                                $reflectionProperty->getName() . ' not found, please, respect convention or add patch 
                                "@PM\Patch\Accessor(propertyName). Our searching: get' . ucfirst($reflectionProperty->getName()));
                        }else{
                            $this->dependency[$type]['mutator'] = $accessor;
                        }
                    }
                }
            }
        }
    }


    /**
     * Return type of dependency
     *
     * @param ReflectionProperty $reflectionProperty
     * @param ReflectionClass $reflectionClass
     * @return string|null
     * @throws NotFoundException
     */
    private function getTypeDependency(ReflectionProperty $reflectionProperty, ReflectionClass $reflectionClass): ?string
    {

        $comment = $reflectionProperty->getDocComment();


        if(strpos($comment, '@PM\Dependency(') !== false){
            $floorOne = explode('@PM\Dependency(', $comment);
            $type = trim(explode(')', $floorOne[1])[0]);

            if(!class_exists($type)){
                throw new NotFoundException('BuilderPersistsManager encountered an error: property "' . $reflectionProperty->getName() . ' with type: "' . $type . '" is not class in ' . $reflectionClass->getName());
            }else{
                return $type;
            }
        }else{
            if(class_exists($reflectionProperty->getType()->getName())){
                return $reflectionProperty->getType()->getName();
            }

            throw new NotFoundException('BuilderPersistsManager encountered an error: property "' . $reflectionProperty->getName() . ' with type: "' . $reflectionProperty->getType()->getName() . '" is not class in ' . $reflectionClass->getName());
        }
    }


    /**
     * Return type of this property
     *
     * @param ReflectionProperty $reflectionProperty
     * @return string
     * @throws NotFoundException
     */
    private function getTypeProperty(ReflectionProperty $reflectionProperty, ReflectionClass $reflectionClass): string
    {

        if($reflectionProperty->getType() === null){
            throw new NotFoundException('BuilderPeristsManager encountered an error: the type of property "' . $reflectionProperty->getName() . '" of the class ' . $reflectionClass->getName() . ' is unresolved');
        }

        $type = $reflectionProperty->getType()->getName();


        if($type === null){
            return 'mixed';
        }else{
            return $type;
        }
    }


    /**
     * Return column if she's defined
     *
     * @param ReflectionProperty $reflectionProperty
     * @param ReflectionClass $reflectionClass
     * @return string|null
     * @throws NotFoundException
     */
    private function getColumnProperty(ReflectionProperty $reflectionProperty, ReflectionClass $reflectionClass): ?string
    {
        $comment = $reflectionProperty->getDocComment();

        if(strpos($comment, self::$comment['column'])) {
            preg_match('/@PM\\\Column\([a-zA-Z0-9-_&\/\\\~@#]+\)/', $comment, $output);

            if (!empty($output[0])) {
                return str_replace(['@PM\\Column(', ')'], '', $output[0]);
            } else {
                throw new NotFoundException('BuilderPersistsManager encountered an error: column name could not be resolved for ' . $reflectionClass->getName() . '::'. $reflectionProperty->getName() . ', but exists, please, verify your syntax');
            }
        }elseif(isset($this->extends[$reflectionProperty->getName()])){
            return $this->extends[$reflectionProperty->getName()];
        }

        return null;

    }


    /**
     * Return a method for access the property
     *
     * @param ReflectionClass $reflectionClass
     * @param ReflectionProperty $reflectionProperty
     * @param string $scope
     * @return string
     * @throws NotFoundException
     * @throws ReflectionException
     */
    private function getAccessorProperty(ReflectionClass $reflectionClass, ReflectionProperty $reflectionProperty, string $scope): string
    {


        //If scope is public, accessor is the variable name, else, search a accessor method with recovery process if an error occured
        if($scope === 'public') {
            return $reflectionProperty->getName();
        }else {
            try {
                return $reflectionClass->getMethod('get' . ucfirst($reflectionProperty->getName()))->getName();
            } catch (ReflectionException $rf) {

                $accessor = $this->searchPatch($reflectionProperty->getName(), $reflectionClass, 'Accessor');

                if ($accessor === null) {
                    throw new NotFoundException('BuilderPersistsManager encountered an error: accessor for property ' . $reflectionProperty->getName() . ' not found, please, respect convention or add patch "@PM\Patch\Accessor(propertyName). Our searching: get' . ucfirst($reflectionProperty->getName()));
                }

                return $accessor;
            }
        }
    }


    /**
     * Return method for mutate the property
     *
     * @param ReflectionClass $reflectionClass
     * @param ReflectionProperty $reflectionProperty
     * @param string $scope
     * @return string
     * @throws NotFoundException
     * @throws ReflectionException
     */
    private function getMutatorProperty(ReflectionClass $reflectionClass, ReflectionProperty $reflectionProperty, string $scope): string
    {

        //If scope is public, mutator is the variable name, else, search a mutator method with recovery process if an error occured
        if($scope === 'public') {
            return $reflectionProperty->getName();
        }else{
            try {
                return $reflectionClass->getMethod('set' . ucfirst($reflectionProperty->getName()))->getName();
            } catch (ReflectionException $rf) {

                $accessor = $this->searchPatch($reflectionProperty->getName(), $reflectionClass, 'Mutator');

                if ($accessor === null) {
                    throw new NotFoundException('BuilderPersistsManager encountered an error: mutator for property ' .
                        $reflectionProperty->getName() . ' not found, please, respect convention or add patch 
                        "@PM\Patch\Mutator(propertyName). Our searching: set' . ucfirst($reflectionProperty->getName()));
                }

                return $accessor;
            }
        }
    }


    /**
     * Return scope of the property
     *
     * @param ReflectionProperty $reflectionProperty
     * @return string
     */
    private function getScopeProperty(ReflectionProperty $reflectionProperty): string
    {
        if($reflectionProperty->isPublic()){
            return 'public';
        }elseif($reflectionProperty->isProtected()){
            return 'protected';
        }else{
            return 'private';
        }
    }


    /**
     * @param ReflectionProperty[] $properties
     * @param ReflectionClass $reflectionClass
     * @throws ReflectionException
     * @throws NotFoundException
     */
    private function getKeyArray(array $properties, ReflectionClass $reflectionClass): void
    {

        foreach ($properties as $value){
            $reflectionProperty = new ReflectionProperty($reflectionClass->getName(), $value->getName());

            $comment = $reflectionProperty->getDocComment();

            if(strpos($comment, self::$comment['keyArray']) !== false){
                $this->keyArray = $reflectionProperty->getName();
            }
        }

        if(!isset($this->keyArray)){
            throw new NotFoundException('BuilderPersistsManager encountered an error: keyArray property is not found in ' .
                $this->className . ', please, verify your syntaxe or add that');
        }
    }


    /**
     * Search patch for error not found
     *
     * @param string $name
     * @param ReflectionClass $reflectionClass
     * @param string $type
     * @return string
     * @throws ReflectionException
     * @throws NotFoundException
     */
    private function searchPatch(string $name, ReflectionClass $reflectionClass, string $type): string
    {

        foreach ($reflectionClass->getMethods() as $value){
            $reflectionMethod = new \ReflectionMethod($reflectionClass->getName(), $value->getName());

            if(strpos($reflectionMethod->getDocComment(), self::$comment['patch'] . '\\' . $type . '(' . $name . ')')){
                return $reflectionMethod->getName();
            }
        }

        throw new NotFoundException('BuilderPersistsManager encountered an error: accessor of ' . $name . ' property not found, please, create an patch with this format: @PM\Patch\Accessor(' . $name . ') or respect the convention.<br> Our search: get' . ucfirst($name) . ' or set' . ucfirst($name) . ' and '. self::$comment["patch"] . '\\' . $type . '(' . $name . ')');
    }


    /**
     * Build array formated for work
     *
     * @return array
     */
    private function buildArrayCache(): array
    {

        $array = null;

        if(file_exists(self::CACHE_PATH)){
            $array = require self::CACHE_PATH;
            $array = unserialize($array);
        }else{
            $array = array();
        }

        if(isset($array[$this->className])){
            unset($array[$this->className]);
        }


        $array[$this->className]['keyArray'] = $this->keyArray;
        $array[$this->className]['table'] = $this->table;

        foreach ($this->property as $value){
            $array[$this->className]['property'][] = [
                'column' => $value['column'],
                'accessor' => $value['accessor'],
                'mutator' => $value['mutator'],
                'type' => $value['type'],
                'scope' => $value['scope'],
            ];
        }

        if(isset($this->dependency)) {

            $array[$this->className]['dependency'] =  $this->dependency;

        }

        return $array;
    }


    /**
     * Write in file cache
     */
    private function pushCacheData(): void
    {
        $array = $this->buildArrayCache();

        $content = "<?php
        return '" . serialize($array) . "';";

        file_put_contents(self::CACHE_PATH, $content);
    }

}
