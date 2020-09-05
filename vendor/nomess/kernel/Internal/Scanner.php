<?php

namespace Nomess\Internal;

trait Scanner
{

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
