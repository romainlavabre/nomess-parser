<?php


namespace Nomess\Components\EntityManager;


interface EntityManagerInterface
{
    
    public function find( string $classname, ?string $idOrSql = NULL, ?array $parameters = NULL, bool $lock = FALSE );
    
    
    public function persists( object $object ): self;
    
    
    public function delete( object $object ): self;
    
    
    public function register(): bool;
}
