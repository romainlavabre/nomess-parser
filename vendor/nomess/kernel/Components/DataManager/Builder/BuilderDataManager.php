<?php 

namespace Nomess\Components\DataManager\Builder;


use Nomess\Exception\MissingConfigurationException;
use Nomess\Exception\NotFoundException;
use Nomess\Exception\SyntaxException;
use Nomess\Internal\Scanner;

class BuilderDataManager
{

    use Scanner;

    private const DIR                   = ROOT . 'App/src/Modules/';
    private const CACHE_PATH            = ROOT . 'App/var/cache/dm/datamanager.xml';

    private ?array $tabDir = array();

    /**
     * @throws MissingConfigurationException
     * @throws NotFoundException
     * @throws SyntaxException
     * @throws \ReflectionException
     */
    public function builderManager() : void
    {
        $this->tabDir = $this->scanRecursive();

        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n\n<data>\n";
        foreach($this->tabDir as $path){
            $present = strpos($path, 'Entity');

            if($present !== false){
                $tabFile = scandir($path);

                
                foreach($tabFile as $content){
                    
                    if($content !== '.' 
                        && $content !== '..'
                        && is_file($path . $content)){
                            
                        $cls = $this->getComment($this->getNamespace($path . $content) . '\\' . $this->getClassName($path . $content));


                        if($cls !== null){
                            $xml = $xml . "\t<class class=\"" . $cls->getClassName() . "\">\n";

                            if(!empty($cls->getKey())){
                                $xml = $xml . "\t\t<session>\n";
                                $xml = $xml . "\t\t\t<key>" . $cls->getKey() . "</key>\n";
                                
                                if(!empty($cls->getKeyArray())){
                                    $xml = $xml . "\t\t\t<keyArray>" . $cls->getKeyArray() . "</keyArray>\n";
                                }

                                if(!empty($cls->getDbDepend())){
                                    foreach($cls->getSesDepend() as $key => $value){
                                        $tabValue = explode('::', $key);

                                        $xml = $xml . "\t\t\t<depend class=\"" . $tabValue[0] .  "\" set=\"" . $value . "\" get=\"" . $tabValue[1] . "\"/>\n";
                                    }
                                }

                                $xml = $xml . "\t\t</session>\n";
                            }

                            if(!empty($cls->getBase())){
                                $xml = $xml . "\t\t<base class=\"" . $cls->getBase() . "\">\n";
                                
                                if(!empty($cls->getInsert())){
                                    $xml = $xml . "\t\t\t<insert>" . $cls->getInsert() . "</insert>\n";
                                }

                                if(!empty($cls->getDbDepend())){
                                    foreach($cls->getDbDepend() as $key => $value){
                                        $tabValue = explode('::', $key);

                                        if(!isset($tabValue[1])){
                                            throw new SyntaxException('Erreur de syntaxe dans la class ' . $cls->getClassName() . ' pour la clause @database{"depend", "' . $tabValue[0] .  '"}<br><br>Vous pourriez avoir oublié la methode ?<br>Rappel: "@database{"depend", "Full\Qualified\Class::methodName"}');
                                        }

                                        if(strpos($tabValue[1], '()') !== false){
                                            throw new SyntaxException('Erreur de syntaxe dans la class ' . $cls->getClassName() . ' pour la clause @database{"depend", "' . $tabValue[0] .  '"}<br>Unexcepted "()"');
                                        }

                                        $xml = $xml . "\t\t\t<depend class=\"" . $tabValue[0] .  "\" set=\"" . $value . "\" get=\"" . $tabValue[1] . "\"/>\n";
                                    }
                                }

                                if(!empty($cls->getNoTransaction())){

                                    foreach($cls->getNoTransaction() as $key => $value){
                                        $noTransactionContent = "\t\t\t<transaction ";
                                        $noTransactionContent = $noTransactionContent . "name=\"" . $key . "\" ";


                                        foreach($value as $no){
                                            $noTransactionContent = $noTransactionContent . $no . "=\"false\" " ;
                                        }

                                        $noTransactionContent = $noTransactionContent . "/>\n";
                                        $xml = $xml . $noTransactionContent;
                                    }
                                }

                                if(!empty($cls->getAlias())){
                                    foreach($cls->getAlias() as $key => $value){
                                        $alias = "\t\t\t<alias method=\"" . $key . "\" alias=\"" . $value . "\"/>\n";
                                        $xml = $xml . $alias;
                                    }
                                }

                                $xml = $xml . "\t\t</base>\n";
                            }


                             $xml = $xml . "\t</class>\n";
                        }
                    }
                }
            }
        }

        $xml = $xml . "</data>";

        if(!file_put_contents(self::CACHE_PATH, $xml)){
            throw new NotFoundException('BuilderDataManager encountered an error: Impossible to access to cache file');
        }
    }


    /**
     *
     * @param string $path
     * @return string
     * @throws NotFoundException
     */
    private function getClassName(string $path) : string
    {
        if(@$file = file($path)){
            foreach($file as $line){
                $exist = strpos($line, 'class');

                if($exist !== false){
                    
                    $tab = explode(' ', $line);

                    $i = 0;

                    foreach($tab as $value){
                        if($value === 'class'){
                            return $tab[$i + 1];
                        }

                        $i++;
                    }
                }
            }

            throw new NotFoundException('BuilderDataManager encountered an error: the class name can\'t be resolved in file . ' . $path);
        }else{
            throw new NotFoundException('BuilderDataManager encountered an error: the file ' . $file . ' can\'t be resolved');
        }
    }


    /**
     *
     * @param string $path
     * @return string|null
     * @throws NotFoundException
     */
    private function getNamespace(string $path) : ?string
    {
        if(@$file = file($path)){
            foreach($file as $line){
                $exist = strpos($line, 'namespace');

                if($exist !== false){
                    
                    $floorOne = explode(' ', $line);
                    $floorTwo = explode(';', $floorOne[1]);
                    return $floorTwo[0];
                }
            }
        }else{
            throw new NotFoundException('BuilderDataManager encountered an error: the file ' . $file . ' could not be opened');
        }
    }

    /**
     * @param string $className
     * @return DocComment|null
     * @throws MissingConfigurationException
     * @throws SyntaxException
     * @throws \ReflectionException
     */
    private function getComment(string $className) : ?DocComment
    {
        $util = false;
        
        $reflection = new \ReflectionClass($className);
        
        $cls = new DocComment($className);
        
        $comment = $reflection->getDocComment();

        $tabHead = explode('*', $comment);
        
        foreach($tabHead as $value){

            if(strpos($value, '@session') !== false){
                $floorOne = explode('"', $value);
                
                $cls->setKey($floorOne[1]);
                $util = true;
            }

            if(strpos($value, '@database')){
                $floorOne = explode('"', $value);
                
                if(count($floorOne) <= 3){
                    $cls->setBase($floorOne[1]);
                    $util = true;
                }else{
                    if(isset($floorOne[3])){
                        $cls->setAlias($floorOne[1], $floorOne[3]);
                    }else{
                        throw new SyntaxException('BuilderDataManager: syntaxe error in the class ' . $className);
                    }
                }
            }
        }

        $noTransaction = array();


        foreach($reflection->getProperties() as $value){

            $line = explode('*', $value->getDocComment());


            foreach($line as $propComment){
                $floorOne = explode('"', $propComment);


                $get = null;
                $set = null;

                if($value->isPrivate() || $value->isProtected()){
                    $get = 'get' . ucfirst($value->getName());
                    $set = 'set' . ucfirst($value->getName());
                }else{
                    $get = $value->getName();
                    $set = $value->getName();
                }

                if(strpos($propComment, '@session') !== false){


                    if($floorOne[1] === 'keyArray'){
                        $cls->setKeyArray($get);
                        $util = true;
                    }

                    if($floorOne[1] === 'depend'){
                        $get = $floorOne[3];
                        $cls->setSesDepend($get, $set);
                        $util = true;
                    }
                    
                }

                if(strpos($propComment, '@database')){

                    if($floorOne[1] === 'insert'){
                        $cls->setInsert($set);
                        $util = true;
                    }

                    if($floorOne[1] === 'depend'){
                        $get = $floorOne[3];
                        $cls->setDbDepend($get, $set);
                        $util = true;
                    }

                    if($floorOne[1] === 'noCreate'){
                        $noTransaction[$value->getName()][] = 'noCreate';
                    }

                    if($floorOne[1] === 'noUpdate'){
                        $noTransaction[$value->getName()][] = 'noUpdate';
                    }

                    if($floorOne[1] === 'noDelete'){
                        $noTransaction[$value->getName()][] = 'noDelete';
                    }
                }
            }
        }

        $cls->setNoTransaction($noTransaction);

        if($util === true){

            if((!empty($cls->getSesDepend()) || !empty($cls->getKeyArray())) && empty($cls->getKey())){
                throw new MissingConfigurationException('BuilderDataManager encountered an error: session key expected for this class: ' . $cls->getClassName());
            }

            if((!empty($cls->getDbDepend()) || !empty($cls->getInsert())) && empty($cls->getBase())){
                throw new MissingConfigurationException('BuilderDataManager encountered an error: class of persistence expected for this class: '  . $cls->getClassName());
            }
            
            
            return $cls;
        }

        return null;
    }
}
