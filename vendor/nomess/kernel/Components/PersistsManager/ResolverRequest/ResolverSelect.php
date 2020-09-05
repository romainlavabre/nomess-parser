<?php


namespace Nomess\Components\PersistsManager\ResolverRequest;


use InvalidArgumentException;
use Nomess\Components\PersistsManager\Resolver;


class ResolverSelect extends Resolver
{


    /**
     * Contains the last class where data insert is build
     */
    private ?string $internalCursor;

    /**
     * Contains class name
     */
    public ?string $className;


    public function execute(): void
    {

        $columnInfo = $this->parserColumn();

        $this->buildCache($columnInfo);
        $this->registerInitialConfig();
        $this->purge();
    }



    /**
     * Return column info
     *
     * @return array
     */
    private function parserColumn(): array
    {

        $array = array();

        $floorOne = explode('FROM', $this->request);
        $list = str_replace('SELECT', '', $floorOne[0]);


        //Search an "Alias.*"
        preg_match('/[.]\.*/', $list, $output);

        if($output === 1 || $output === true){
            throw new InvalidArgumentException('Resolver encountered an error: nomess doesn\'t accept a "alias.*" format');
        }

        //Search the * sign
        if(strpos($list, '*') !== false){
            foreach($this->propertyMapping as $value){
                $array[$value['column']] = [
                    'column' => $value['column'],
                    'alias' => null,
                    'prefix' => null
                ];
            }

            //Search an bad convention
        }elseif (strpos($list, 'as') !== false){
            throw new InvalidArgumentException('Resolver Encountered an error: nomess have found "as" but not "AS", please, capitalize');
        }else {
            $tabColumn = explode(',', $list);

            foreach ($tabColumn as $column) {

                //If find an alias column
                if(strpos($column, 'AS') !== false){
                    $group = explode('AS', $column);


                    $return = $this->getExplodeColumn($group[0]);

                    $array[trim($group[0])] = [
                        'column' => $return['column'],
                        'alias' => trim($group[1]),
                        'prefix' => $return['prefix']
                    ];

                }else{

                    $return = $this->getExplodeColumn($column);

                    $array[$column] = [
                        'column' => $return['column'],
                        'alias' => null,
                        'prefix' => $return['prefix']
                    ];
                }
            }
        }

        return $this->getRelationTable($array);
    }


    /**
     * Extract column the column and prefix
     *
     * @param string $column
     * @return array
     */
    private function getExplodeColumn(string $column): array
    {

        $array = null;

        //If alias table found
        if(strpos($column, '.') !== false){
            $groupPrefixColumn = explode('.', $column);

            $array = [
                'column' => trim($groupPrefixColumn[1]),
                'prefix' => trim($groupPrefixColumn[0])
            ];
        }else{

            $array = [
                'column' => trim($column),
                'prefix' => null
            ];
        }

        return $array;
    }


    /**
     * Mapping the suffix by table
     *
     * @param array $tabColumn
     * @return array
     */
    private function getRelationTable(array $tabColumn): array
    {
        $searchPortion = explode('FROM', $this->request);

        preg_match_all('/[a-zA-Z0-9-_&\/\\\~@#]+\s*AS\s*[A-Za-z0-9-_\.]+/', $searchPortion[1], $table);


        if(!empty($table)) {
            foreach ($table[0] as $value) {
                $tmp = explode('AS', $value);

                if (isset($this->dependency)) {
                    foreach ($this->dependency as $className => $configuration) {

                        foreach ($configuration as $array) {
                            if ($array['table'] === trim($tmp[0])) {

                                foreach ($tabColumn as &$arrayColumn) {

                                    $prefixFind = trim($tmp[1]);

                                    if ($arrayColumn['prefix'] === $prefixFind) {
                                        $arrayColumn['objectRelation'] = $className;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //If relation object doesn't exist, column is to object target
        foreach ($tabColumn as &$value){
            if(!array_key_exists('objectRelation', $value)){
                $value['objectRelation'] = $this->className;
            }
        }

        return $tabColumn;
    }


    private function buildCache(array $columnInfo): void
    {

        $classNameCache = $this->generateClassName($this->className . "::" . $this->method);

        $content = $this->getBeginningClass($classNameCache);

        $content .= $this->getObjectTarget($columnInfo);

        if(isset($this->dependency)) {
            foreach ($this->dependency as $dependencyClassName => $unused) {
                $content .= $this->getDependency($dependencyClassName, $columnInfo);
            }
        }


        $content .= $this->getEndClass();


        $this->registerCache($content, $classNameCache);
    }


    /**
     * Return php code for beginning class
     *
     * @param string $classNameCache
     * @return string
     */
    private function getBeginningClass(string $classNameCache): string
    {
        $parameter = "Nomess\Database\IPDOFactory \$instance, Nomess\Container\Container \$container";


        //Add arbitrary parameters given
        $parameter = $this->adjustParameter($parameter);


        return "<?php\r
        \r
                
        class " . $classNameCache . "
        {
            public function execute(" . $parameter . "): array
            {
            
                \$database = \$instance->getConnection('" . $this->idConfig . "');
                
                " . $this->buildFileRequest() . "
                
                
                \$tab = array();
                
                while(\$data = \$req->fetch(\PDO::FETCH_ASSOC)){";
    }


    /**
     * Return php of end class
     *
     * @return string
     */
    private function getEndClass(): string
    {

        return "
                            \$tab[$" . $this->getOnlyClassName($this->className) . "->" . $this->propertyMapping[$this->getKeyArray($this->className)]['accessor'] .
            (
            ($this->propertyMapping[$this->getKeyArray($this->className)]['scope'] === 'public')
                ? ""
                : "()"
            ) . "] = $" . $this->getOnlyClassName($this->className) . ";\r
                        \r\t\t\t\t}
                    return \$tab;
            \r\t\t\t}
            
        \r\t\t}";
    }


    /**
     *
     * Return object target
     * @param array $columnInfo
     * @return string
     */
    private function getObjectTarget(array $columnInfo): string
    {
        return "
            
            \$" . $this->getOnlyClassName($this->className) . " = null;
            
            if(!isset(\$tab[\$data['" . $this->getKeyArrayClean($this->className, $columnInfo) . "']])){
                \$" . $this->getOnlyClassName($this->className) . " = \$container->make(" . $this->className . "::class);\r
                " . $this->getMutatorList($this->className, $columnInfo) . "
            }else{
                \$" . $this->getOnlyClassName($this->className) . " = \$tab[\$data['" . $this->getKeyArrayClean($this->className, $columnInfo) . "']];
            }
        
        
        ";
    }


    /**
     * Return php code for dependency
     *
     * @param string $className
     * @param array $columnInfo
     * @return string
     */
    private function getDependency(string $className, array $columnInfo): string
    {
        return "\n\n\t\t\t\tif(isset(\$data['" . $this->getKeyArrayClean($className, $columnInfo) . "'])){
            $" . $this->getOnlyClassName($className) . " = \$container->make(" . $className . "::class);
            " . $this->getMutatorList($className, $columnInfo) . "
            
            " . $this->getSetterLineScopeResolver($this->className, $this->cache[$this->className]['dependency'][$className]['mutator'], $this->cache[$this->className]['dependency'][$className]['scope'], '$' . $this->getOnlyClassName($className)) . "
        }";
    }


    /**
     * Return of actual keyArray
     *
     * @param string|null $className
     * @return string|null
     * @throws \Exception
     */
    private function getKeyArrayClean(?string $className, array $columnInfo): string
    {

        $column = $this->getKeyArray($className);


        foreach ($columnInfo as $key => $value){
            if($value['column'] === $column && $className === $value['objectRelation']){
                if($value['alias'] !== null){
                    return $value['alias'];
                }else{
                    if($value['prefix'] !== null){
                        return $key;
                    }else{
                        return $value['column'];
                    }
                }
            }
        }

        return $column;
    }


    /**
     * Return php code of setters method
     *
     * @param string $className
     * @param array $columnInfo
     * @return string
     */
    private function getMutatorList(string $className, array $columnInfo): ?string
    {
        $tabProperty = $this->getConfiguration($className);

        $content = null;

        $passed = array();

        foreach ($columnInfo as $fullColumnName => $value){
            foreach ($tabProperty as $tabColumn){

                if($tabColumn['column'] === $value['column'] && $value['objectRelation'] === $className && !array_key_exists($fullColumnName, $passed)){
                    if($tabColumn['type'] === 'array'){
                        $content .= $this->getArrayPortion($columnInfo, $tabColumn, $fullColumnName, $className);
                    }else{
                        $content .= $this->getSetterLineScopeResolver($className, $tabColumn['mutator'], $tabColumn['scope'], '$data["' . $this->getAbstractColumn($columnInfo, $fullColumnName) . '"]');
                    }

                    $passed[$fullColumnName] = null;
                }
            }
        }


        return $content;
    }


    /**
     * Return a good column for mutator
     *
     * @param array $columnInfo
     * @param string $fullColumnName
     * @return string
     */
    private function getAbstractColumn(array $columnInfo, string $fullColumnName): string
    {
        if($columnInfo[$fullColumnName]['alias'] !== null){
            return $columnInfo[$fullColumnName]['alias'];
        }else{
            if($columnInfo[$fullColumnName]['prefix'] !== null){
                return key($columnInfo);
            }else{
                return $columnInfo[$fullColumnName]['column'];
            }
        }
    }


    /**
     * Return the property configuration for className parameter
     *
     * @param string $className
     * @return array
     */
    private function getConfiguration(string $className): array
    {
        return $this->cache[$className]['property'];
    }


    /**
     * Return php code for array property
     *
     * @param array $columnInfo
     * @param array $tabColumn
     * @param string $fullColumnName
     * @param string $fullClassName
     * @return string
     */
    private function getArrayPortion(array $columnInfo, array $tabColumn, string $fullColumnName, string $fullClassName): string
    {

        $columnName = $this->getAbstractColumn($columnInfo, $fullColumnName);

        return "\n\t\t\t\t\tif(isset(\$data['" . $columnName . "']) && !empty(\$data['" . $columnName . "'])){
                            
                        \$tmp = unserialize(\$data['" . $columnName . "']);
                        
                        try{
                            foreach(\$tmp as \$value){
                                " . $this->getSetterLineScopeResolver($fullClassName, $tabColumn['mutator'], $tabColumn['scope'], '$value') . "
                            }  
                        }catch(\Throwable \$th){
                        \t" . $this->getSetterLineScopeResolver($fullClassName, $tabColumn['mutator'], $tabColumn['scope'], '$tmp') . "
                        }
                    }\n\n
                    ";
    }


    /**
     * Return line if setting object in resolving scope
     *
     * @param string $className
     * @param string $mutator
     * @param string $scope
     * @param string $insertion
     * @return string
     */
    private function getSetterLineScopeResolver(string $className, string $mutator, string $scope, string $insertion): string
    {
        if($scope === 'public'){
            return '$' . $this->getOnlyClassName($className) . '->' . $mutator . ' = ' . $insertion . ";\n\t\t\t\t";
        }else{
            return '$' . $this->getOnlyClassName($className) . '->' . $mutator . '(' . $insertion . ");\n\t\t\t\t";
        }
    }


    protected function purge(): void
    {
        parent::purge();
        $this->suffixTable = array();
        $this->internalCursor = null;
        $this->className = null;
        $this->dataInsert = array();
    }
}
