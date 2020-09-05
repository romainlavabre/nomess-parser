<?php

define('ROOT_PRELOADER', __DIR__);
define('ROOT', str_replace('vendor/nomess/kernel', '', ROOT_PRELOADER));

require str_replace('nomess/kernel', '', ROOT_PRELOADER) . '/autoload.php';

class Preloader
{
    public function getFile(): void
    {
        $array = $this->scanRecursive(ROOT_PRELOADER . '/');
        $this->requireFile($array);
        $array = $this->scanRecursive(ROOT . 'src');
        $this->requireFile($array);
        
    }
    
    public function requireFile(array $array): void
    {
        foreach($array as $dir){
            foreach(scandir($dir) as $file){
                if(strpos($file, '.php') !== FALSE && strpos($file, 'Exception') === FALSE
                   && strpos($dir, 'Tools') === FALSE){
                    try {
                        require_once $dir . $file;
                    }catch(Throwable $th){}
                }
            }
        }
    }
    
    /**
     * Return tree of directory 'App/src/Controllers'
     *
     * @param string $dir
     * @return array
     */
    public function scanRecursive(string $dir) : array
    {
        $pathDirSrc = $dir;
        
        $tabGeneral = scandir($pathDirSrc);
        
        $tabDirWait = array();
        
        $dir = $pathDirSrc;
        
        $noPass = count(explode('/', $dir));
        
        do{
            $stop = false;
            
            do{
                $tabGeneral = scandir($dir);
                $dirFind = false;
                
                for($i = 0; $i < count($tabGeneral); $i++){
                    if(is_dir($dir . $tabGeneral[$i] . '/') && $tabGeneral[$i] !== '.' && $tabGeneral[$i] !== '..'){
                        if(!$this->controlDir($dir . $tabGeneral[$i] . '/', $tabDirWait)){
                            $dir = $dir . $tabGeneral[$i] . '/';
                            $dirFind = true;
                            break;
                        }
                    }
                }
                
                if(!$dirFind){
                    $tabDirWait[] = $dir;
                    $tabEx = explode('/', $dir);
                    unset($tabEx[count($tabEx) - 2]);
                    $dir = implode('/', $tabEx);
                }
                
                if(count(explode('/', $dir)) < $noPass){
                    $stop = true;
                    break;
                }
            }
            while($dirFind === true);
        }
        while($stop === false);
        
        return $tabDirWait;
    }
    
    
    private function controlDir(string $path, array $tab) : bool
    {
        foreach($tab as $value){
            if($value === $path){
                return true;
            }
        }
        
        return false;
    }
}

(new Preloader())->getFile();
