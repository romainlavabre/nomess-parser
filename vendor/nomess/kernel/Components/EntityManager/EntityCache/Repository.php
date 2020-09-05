<?php


namespace Nomess\Components\EntityManager\EntityCache;


class Repository
{
    
    public const  PATH_STATUS = ROOT . 'var/cache/ce/status';
    
    public const  PATH_CACHE  = ROOT . 'var/cache/ce/';
    
    public const PATH_CONFIG = ROOT . 'config/components/EntityManager.php';
    
    private array                        $status        = array();
    private array                        $store         = array();
    private array                        $cloned        = array();
    private array                        $removed       = array();
    private bool                         $statusChanged = FALSE;
    
    
    public function __construct()
    {
        if( file_exists( self::PATH_STATUS ) ) {
            $this->status = unserialize( file_get_contents( self::PATH_STATUS ) );
        }
    }
    
    
    public function addInStore( object $object ): void
    {
        $this->store[get_class( $object )][$object->getId()] = $object;
    }
    
    
    public function getToStore( string $classname, int $id )
    {
        return $this->store[$classname][$id];
    }
    
    
    public function getStore(): array
    {
        return $this->store;
    }
    
    
    public function storeHas( string $classname, int $id ): bool
    {
        return isset( $this->store[$classname][$id] );
    }
    
    
    public function addClone( object $object ): void
    {
        $this->cloned[get_class( $object )][$object->getId()] = $object;
    }
    
    
    public function getCloned(): array
    {
        return $this->cloned;
    }
    
    
    public function addRemoved( object $object ): void
    {
        $this->removed[] = $this->getFilename( get_class( $object ), $object->getId() );
        unset( $object );
    }
    
    
    public function getRemoved(): array
    {
        return $this->removed;
    }
    
    
    public function addSelectAll( string $classname ): void
    {
        $this->status['class'][$classname]['select_all'] = TRUE;
        $this->statusChanged                             = TRUE;
    }
    
    
    public function isAllSelected( string $classname ): bool
    {
        return isset( $this->status['class'][$classname]['select_all'] ) && $this->status['class'][$classname]['select_all'];
    }
    
    
    public function resetStatus(): void
    {
        $this->status['creation_time'] = time();
        $this->status['class']         = NULL;
        $this->statusChanged           = TRUE;
    }
    
    
    public function isStatusChanged(): bool
    {
        return $this->statusChanged;
    }
    
    
    public function getStatus(): array
    {
        return $this->status;
    }
    
    
    public function mustRevalideCache(): bool
    {
        $config = require self::PATH_CONFIG;
        
        if( !array_key_exists( 'creation_time', $this->status ) ) {
            $this->resetStatus();
            
            return FALSE;
        }
        
        return time() > ( $config['life_time'] + $this->status['creation_time'] );
    }
    
    
    public function scanCache(): array
    {
        $list = array();
        
        foreach( scandir( self::PATH_CACHE ) as $file ) {
            if( $file !== '.' && $file !== '..' && $file !== 'status' ) {
                $list[] = self::PATH_CACHE . $file;
            }
        }
        
        return $list;
    }
    
    
    public function removeFile( string $filename ): void
    {
        if( file_exists( $filename ) ) {
            unlink( $filename );
        }
    }
    
    
    public function getIdByFilename( string $filename ): int
    {
        return explode( '__', $filename )[1];
    }
    
    
    public function getClassnameByFilename( string $filename ): string
    {
        return str_replace( '_', '\\', explode( '__', $filename )[0] );
    }
    
    
    public function getFilename( string $classname, int $id ): string
    {
        return self::PATH_CACHE . str_replace( '\\', '_', $classname ) . '__' . $id;
    }
}
