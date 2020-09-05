<?php

require 'function-Installer.php';

(new Filter())->generate();

class Filter
{
    private const PATH              = 'src/Filters/';


    public function generate()
    {
        do{
            $filtername = rdl("Precise the name of filter: ");
        }while($filtername === NULL);

        file_put_contents(self::PATH . ucfirst($filtername) . 'Filter.php', $this->getContent($filtername));

        echo 'Filter generate';
    }

    private function getContent(string $name): string
    {
        return "<?php

namespace App\Filters;

use Nomess\Annotations\Filter;
use Nomess\Manager\FiltersInterface;

/**
 * @Filter(\"your_regex_here\")
 */
class " . ucfirst($name) . "Filter implements FiltersInterface
{
    
    public function filtrate(): void
    {
        /* 
         * TODO create your rule
         *  You can use the dependency injection
         *  Use ResponseHelper for send an response
         */
    }
}";
    }
}
