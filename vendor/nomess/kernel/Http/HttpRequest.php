<?php

namespace Nomess\Http;

class HttpRequest
{
    
    private const SESSION_DATA = 'nomess_persiste_data';
    private ?array $error      = array();
    private ?array $success    = array();
    private ?array $parameters = array();
    private bool $block_success = FALSE;
    
    
    public function __construct()
    {
        
        if( isset( $_SESSION[self::SESSION_DATA] ) ) {
            foreach( $_SESSION[self::SESSION_DATA] as $key => $data ) {
                if( $key === 'error' ) {
                    $this->error = $data;
                } elseif( $key === 'success' ) {
                    $this->success = $data;
                } else {
                    $this->parameters[$key] = $data;
                }
            }
            
            unset( $_SESSION[self::SESSION_DATA] );
        }
    }
    
    
    /**
     * Add an error
     *
     * @param string $message
     */
    public function setError( string $message ): void
    {
        $this->error[] = $message;
    }
    
    
    /**
     * Add an success
     *
     * @param string $message
     */
    public function setSuccess( string $message ): void
    {
        if(!$this->block_success) {
            $this->success[] = $message;
        }
    }
    
    
    /**
     * Delete all success message
     *
     * @param bool $block
     */
    public function resetSuccess(bool $block = FALSE): void
    {
        $this->success = NULL;
        
        if($block){
            $this->block_success = TRUE;
        }
    }
    
    
    /**
     * Add an parameter of request
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function setParameter( $key, $value ): void
    {
        $this->parameters[$key] = $value;
    }
    
    
    /**
     * Return an parameter from GET or POST, if conflict exists, POST is the default choice
     * If doesn't exists parameter, null is retuned
     *
     * @param string $index
     * @param bool $escape True by default, htmlspecialchars is apply
     * @return mixed
     */
    public function getParameter( string $index, bool $escape = TRUE )
    {
        if( isset( $_POST[$index] ) && $_POST[$index] !== '' ) {
            
            if( $escape === TRUE ) {
                if( is_array( $_POST[$index] ) ) {
    
                    if(count($_POST[$index]) === 1 && isset($_POST[$index][0]) && $_POST[$index][0] === ''){
                        return NULL;
                    }
                    
                    array_walk_recursive( $_POST[$index], function ( $key, &$value ) {
                        $value = htmlspecialchars( $value );
                        $value = trim( $value );
                    } );
                }
                
                
                return $_POST[$index];
            } else {
                return $_POST[$index];
            }
        } elseif( isset( $_GET[$index] ) && $_GET[$index] !== '' ) {
            
            if( $escape === TRUE ) {
                
                if( is_array( $_GET[$index] ) ) {
    
                    if(count($_GET[$index]) === 1 && isset($_GET[$index][0]) && $_GET[$index][0] === ''){
                        return NULL;
                    }
                    
                    array_walk_recursive( $_GET[$index], function ( $key, &$value ) {
                        $value = htmlspecialchars( $value );
                        $value = trim( $value );
                    } );
                }
                
                return $_GET[$index];
            } else {
                return $_GET[$index];
            }
        } elseif( isset( $this->parameters[$index] ) ) {
            return $this->parameters[$index];
        } else {
            return NULL;
        }
    }
    
    
    /**
     * Return all value of POST variable
     *
     * @return array|null
     */
    public function getParameters(): ?array
    {
        return array_merge( [
                                'POST'    => $_POST,
                                'GET'     => $_GET,
                                'success' => $this->success,
                                'error'   => $this->error
                            ], $this->parameters );
    }
    
    
    /**
     * Return the file sended by POST request
     *
     * @param string $index
     * @return array|null
     */
    public function getPart( string $index ): ?array
    {
        if( array_key_exists( $index, $_FILES ) ) {
            if( is_array( $_FILES[$index]['name'] ) ) {
                if( !empty( $_FILES[$index]['name'][0] ) ) {
                    return $_FILES[$index];
                }
                
                return NULL;
            } elseif( !empty( $_FILES[$index]['name'] ) ) {
                return $_FILES[$index];
            }
        }
        
        return NULL;
    }
    
    
    /**
     * Return associate cookie of index variable, null if empty of doesn't exists
     *
     * @param string $index
     * @return mixed
     */
    public function getCookie( string $index )
    {
        if( isset( $_COOKIE[$index] ) && !empty( $_COOKIE[$index] ) ) {
            return $_COOKIE[$index];
        } else {
            return NULL;
        }
    }
    
    
    /**
     * Return all data contained in POST, if espcape worth true, htmlspecialchars will be apply in value (recursively)
     *
     * @param bool $escape
     * @return array|null
     */
    public function getPost( bool $escape = FALSE ): ?array
    {
        if( $escape === TRUE ) {
            array_walk_recursive( $_POST, function ( $key, &$value ) {
                htmlspecialchars( $value );
                $value = trim( $value );
            } );
        }
        
        return $_POST;
    }
    
    
    /**
     * Return all data contained in GET, if espcape worth true, htmlspecialchars will be apply in value (recursively)
     *
     * @param bool $escape
     * @return array|null
     */
    public function getGet( bool $escape = FALSE ): ?array
    {
        if( $escape === TRUE ) {
            array_walk_recursive( $_GET, function ( $key, &$value ) {
                htmlspecialchars( $value );
                $value = trim( $value );
            } );
        }
        
        return $_GET;
    }
    
    
    /**
     * Return all content of $_SERVER variable
     *
     * @return array
     */
    public function getServer(): array
    {
        return $_SERVER;
    }
    
    
    /**
     * Return the "php://input" and decode it
     *
     * @param array $options Options for json_decode function
     * @return mixed
     */
    public function getJsonData( array $options = [] )
    {
        $data = [file_get_contents( 'php://input' )];
        return call_user_func_array( 'json_decode', array_push( $data, $options ) );
    }
    
    
    public function isValidToken(): bool
    {
        if( isset( $_POST['_token'] ) ) {
            if( isset( $_SESSION['app']['_token'] ) ) {
                if( $_POST['_token'] === $_SESSION['app']['_token'] ) {
                    return TRUE;
                }
            }
        } elseif( isset( $_GET['_token'] ) ) {
            if( isset( $_SESSION['app']['_token'] ) ) {
                if( $_GET['_token'] === $_SESSION['app']['_token'] ) {
                    return TRUE;
                }
            }
        }
        
        return FALSE;
    }
    
    
    public function isRequestMethod( string $methodName ): bool
    {
        return $_SERVER['REQUEST_METHOD'] === mb_strtoupper( $methodName );
    }
}
