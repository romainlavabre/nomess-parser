<?php

namespace Nomess\Tools\Twig\Form;


use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CsrfExtension extends AbstractExtension
{
    private const CACHE         = ROOT . 'var/cache/routes/route.php';

    public function getFunctions()
    {
        return [
            new TwigFunction('csrf', [$this, 'csrf']),
        ];
    }

    public function csrf(string $method)
    {
        if(strtoupper($method) === 'POST') {
            echo  '<input type="hidden" name="_token" value="' . $_SESSION['app']['_token'] . '">';
        }else{
            return $_SESSION['app']['_token'];
        }
    }

}
