<?php

namespace Nomess\Http;

use Nomess\Exception\InvalidParamException;
use Nomess\Exception\NomessException;

class HttpSession
{
    
    private const ID_MODULE_SECURITY = 'nomess_session_security';
    private const MODULE_INITIALIZE = 'initialized';
    private const MODULE_USER_AGENT = 'user_agent';
    private const MODULE_TICKET = 'jdf_rt';
    private const MODULE_IP = 'ip';
    private const BIND_TICKET_IP = 'bind_ticket_ip';
    private const RECOVERY_CONFIG = 'recovery_config';
    private const SESSION_SET_LIFE_TIME = 'session_set_life_time';
    
    
    /**
     * Initialize an session
     *
     * @throws NomessException
     */
    public function initSession(): void
    {
        
        if (session_status() === 1) {
            
            ini_set('session.gc_maxlifetime', getenv('NM_SESSION_LIFETIME'));
            session_start();
            if (!isset($_SESSION[self::ID_MODULE_SECURITY])) {
                $_SESSION[self::ID_MODULE_SECURITY] = array();
            }
            
            $this->securityInitialized();
            
            if (isset($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_USER_AGENT])) {
                $this->executedModule();
            }
            
            
        } else if (session_status() === 0) {
            throw new NomessException('Please active the session');
        }
    }
    
    
    /**
     * @param $index
     * @return bool
     */
    public function has($index): bool
    {
        return isset($_SESSION[$index]);
    }
    
    /**
     * Return reference of the entry associate to index
     * Null if doesn't exists
     *
     * @param mixed $index
     * @return mixed|void
     */
    public function &getReference($index)
    {
        if (isset($_SESSION[$index])) {
            return $_SESSION[$index];
        }
    }
    
    /**
     * Return the value assiciate to index, Null if doesn't exists
     *
     * @param mixed $index
     * @return mixed
     */
    public function get($index)
    {
        if (isset($_SESSION[$index])) {
            return $_SESSION[$index];
        } else {
            return null;
        }
    }
    
    
    /**
     * Delete the entry associate to index variable
     *
     * @param string $index
     * @return $this
     */
    public function delete(string $index): self
    {
        if (array_key_exists($index, $_SESSION)) {
            unset($_SESSION[$index]);
        }
        
        return $this;
    }
    
    /**
     * Add value
     *
     * @param mixed $key
     * @param mixed $value
     * @param bool $reset Delete value associate to the key before insertion
     * @return $this
     */
    public function set($key, $value, $reset = false): self
    {
        if ($reset) {
            unset($_SESSION[$key]);
        }
        
        if (\is_array($value)) {
            
            if(!array_key_exists($key, $_SESSION) || !is_array($_SESSION[$key])){
                $_SESSION[$key] = array();
            }
            
            foreach ($value as $keyArray => $valArray) {
                
                $_SESSION[$key][$keyArray] = $valArray;
            }
        } else {
            $_SESSION[$key] = $value;
        }
        
        return $this;
    }
    
    
    /**
     * Modify the lifetime of cookie session
     *
     * @param int $time Time in second
     * @param bool $force Update the cookie even id the value of lifetime is equals to time variable
     * @return $this
     */
    public function setLifeTime(int $time, bool $force = false): self
    {
        
        if (!array_key_exists(self::SESSION_SET_LIFE_TIME, $_SESSION[self::ID_MODULE_SECURITY]) || $force === true) {
            $content = $_SESSION;
            
            session_destroy();
            ini_set('session.gc_maxlifetime', $time);
            session_set_cookie_params($time);
            session_start();
            
            $_SESSION = $content;
            
            $_SESSION[self::ID_MODULE_SECURITY][self::SESSION_SET_LIFE_TIME] = true;
            
        }
        
        return $this;
    }
    
    
    /**
     * @return $this
     */
    public function kill(): self
    {
        $toolbar = NULL;
        
        if(isset($_SESSION['app']['toolbar'])) {
            $toolbar = $_SESSION['app']['toolbar'];
        }
        
        $_SESSION = array();
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $params["path"], $params["domain"],
                  $params["secure"], $params["httponly"]
        );
        
        
        session_destroy();
        
        if(!is_null($toolbar)) {
            $_SESSION['app']['toolbar'] = $toolbar;
        }
        
        return $this;
    }
    
    
    
    /*
     * Configuration des brique de sécurité
     */
    
    
    /**
     * Installation of security module, valid for lifetime of session
     *
     * @param bool $userAgentSystem if TRUE, the useragent will be controlled
     * @param bool $ticketSystem If TRUE, an ticket system will be initialize
     * @param bool $ipSystem If TRUE, the IP ADRESS will be controlled
     * @param bool $bindTicketIp If TRUE, add an felexibility for IP modules, if IP doesn't match but the ticket is valid, the connexion will be accepted
     * @param array[bool $userAgentSystem, bool $ipSystem]|null $recoveryConfig Array of secondary configuration in case of error from ticket modules (Client doesn't accept the cookie)
     * @return $this
     * @throws InvalidParamException
     */
    public function installSecurityModules(bool $userAgentSystem, bool $ticketSystem, bool $ipSystem, bool $bindTicketIp = false, ?array $recoveryConfig = null): self
    {
        
        if ($ticketSystem === true) {
            setcookie('dpr__', 'sdf846dsf68fs3k4f4rs53f8sddfre', time() + 60 * 20, '/');
        }
        
        
        $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_TICKET] = $ticketSystem;
        $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_USER_AGENT] = ($userAgentSystem === true) ? $_SERVER['HTTP_USER_AGENT'] : false;
        $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_IP] = ($ipSystem === true) ? $_SERVER['REMOTE_ADDR'] : null;
        $_SESSION[self::ID_MODULE_SECURITY][self::BIND_TICKET_IP] = $bindTicketIp;
        $_SESSION[self::ID_MODULE_SECURITY][self::RECOVERY_CONFIG] = $recoveryConfig;
        
        if ($recoveryConfig !== null && count($recoveryConfig) !== 2) {
            throw new InvalidParamException('RecoveryConfig must contain exactly 2 parameters: $userAgentSystem and $ipSystem| $ticketSystem and $bindTicketIp will be initialized to false');
        }
        
        return $this;
    }
    
    
    /*
     * Executor
     */
    
    
    /**
     * Execute the modules
     */
    private function executedModule(): void
    {
        $success = true;
        
        if ($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_USER_AGENT] !== false) {
            $success = $this->securityUserAgent();
        }
        
        if ($success === true) {
            if (isset($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_TICKET]) && $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_TICKET] === true) {
                $success = $this->securityTicket();
            }
        }
        
        if ($success === true) {
            if ($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_IP] !== false) {
                $success = $this->securityIpUser();
                if ($success === true && $_SESSION[self::ID_MODULE_SECURITY][self::BIND_TICKET_IP] === true) {
                    $success = true;
                }
            }
        }
        
        
        //r($success);
        if ($success === false) {
            session_regenerate_id(true);
            $_SESSION = array();
        }
    }
    
    
    
    /*
     *  Module de sécurité de session
     */
    
    
    /**
     * Module Initialized
     *
     * @return void
     */
    private function securityInitialized(): void
    {
        if (!isset($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_INITIALIZE])) {
            session_regenerate_id(true);
            $_SESSION[self::ID_MODULE_SECURITY] = array();
            $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_INITIALIZE] = 1;
        }
        
        if(!isset($_SESSION['app']['_token'])){
            $_SESSION['app']['_token'] = md5(uniqid('_token::') . str_shuffle('ABCDEFGHIJ'));
        }
    }
    
    
    /**
     * Module useragent
     *
     * @return bool
     */
    private function securityUserAgent(): bool
    {
        if ($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_USER_AGENT] === $_SERVER['HTTP_USER_AGENT']) {
            return true;
        } else {
            
            return false;
        }
        
    }
    
    
    /**
     * Module ticket
     *
     * @return void
     * @throws InvalidParamException
     */
    private function securityTicket(): bool
    {
        
        if ($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_INITIALIZE] === 1) {
            $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_INITIALIZE]++;
            
            if (!isset($_COOKIE['dpr__'])) {
                $this->installSecurityModules(
                    $_SESSION[self::ID_MODULE_SECURITY][self::RECOVERY_CONFIG][0],
                    false,
                    $_SESSION[self::ID_MODULE_SECURITY][self::RECOVERY_CONFIG][1]
                );
            }
            
            $this->getTicket();
            
            unset($_SESSION[self::ID_MODULE_SECURITY][self::RECOVERY_CONFIG]);
            
            return true;
        } else if (isset($_COOKIE[self::MODULE_TICKET]) && isset($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_TICKET])) {
            
            if ($_COOKIE[self::MODULE_TICKET] === $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_TICKET]) {
                
                $this->getTicket();
                
                return true;
            } else {
                return false;
            }
        } else {
            session_regenerate_id(true);
            
            $this->getTicket();
            return false;
        }
    }
    
    
    /**
     * Create an ticket on demand
     */
    private function getTicket(): void
    {
        $ticket = session_id() . microtime() . rand(0, 9999999999);
        $ticket = md5($ticket);
        
        setcookie(self::MODULE_TICKET, $ticket, time() + (60 * 20), '/');
        $_SESSION[self::ID_MODULE_SECURITY][self::MODULE_TICKET] = $ticket;
    }
    
    
    /**
     * Module IpUser
     *
     * @return bool
     */
    private function securityIpUser(): bool
    {
        if ($_SESSION[self::ID_MODULE_SECURITY][self::MODULE_IP] === $_SERVER['REMOTE_ADDR']) {
            return true;
        } else {
            return false;
        }
    }
}
