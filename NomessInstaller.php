<?php


namespace Nomess\Component\Parser;


use Nomess\Component\Config\ConfigStoreInterface;

/**
 * @author Romain Lavabre <webmaster@newwebsouth.fr>
 */
class NomessInstaller implements \Nomess\Installer\NomessInstallerInterface
{
    
    public function __construct( ConfigStoreInterface $configStore )
    {
    }
    
    
    /**
     * @inheritDoc
     */
    public function container(): array
    {
        return [
            YamlParserInterface::class => YamlParser::class,
            AnnotationParserInterface::class => AnnotationParser::class
        ];
    }
    
    
    /**
     * @inheritDoc
     */
    public function controller(): array
    {
        return [];
    }
    
    
    /**
     * @inheritDoc
     */
    public function cli(): array
    {
        return [];
    }
    
    
    /**
     * @inheritDoc
     */
    public function exec(): ?string
    {
        return NULL;
    }
}
