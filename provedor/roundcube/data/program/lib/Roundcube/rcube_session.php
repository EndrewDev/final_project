<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 |                                                                       |
 | Copyright (C) The Roundcube Dev Team                                  |
 | Copyright (C) Kolab Systems AG                                        |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide database supported session management                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Cor Bosman <cor@roundcu.be>                                   |
 +-----------------------------------------------------------------------+
*/

/**
 * Abstract class to provide database supported session storage
 *
 * @package    Framework
 * @subpackage Core
 */
abstract class rcube_session
{
    protected $config;
    protected $key;
    protected $ip;
    protected $cookie;
    protected $changed;
    protected $start;
    protected $vars;
    protected $now;
    protected $lifetime;
    protected $reloaded     = false;
    protected $appends      = [];
    protected $unsets       = [];
    protected $gc_enabled   = 0;
    protected $gc_handlers  = [];
    protected $cookiename   = 'roundcube_sessauth';
    protected $ip_check     = false;
    protected $logging      = false;
    protected $ignore_write = false;


    /**
     * Blocks session data from being written to database.
     * Can be used if write-race conditions are to be expected
     * @var bool
     */
    public $nowrite = false;

    /**
     * Factory, returns driver-specific instance of the class
     *
     * @param rcube_config $config
     *
     * @return rcube_session Session object
     */
    public static function factory($config)
    {
        // get session storage driver
        $storage = $config->get('session_storage', 'db');

        // class name for this storage
        $class = "rcube_session_{$storage}";

        // try to instantiate class
        if (class_exists($class)) {
            return new $class($config);
        }

        // no storage found, raise error
        rcube::raise_error([
                'code' => 604, 'type' => 'session',
                'line' => __LINE__, 'file' => __FILE__,
                'message' => "Failed to find session driver. Check session_storage config option"
            ],
            true, true
        );
    }

    /**
     * Object constructor
     *
     * @param rcube_config $config
     */
    public function __construct($config)
    {
        $this->config = $config;

        // set ip check
        $this->set_ip_check($this->config->get('ip_check'));

        // set cookie name
        if ($name = $this->config->get('session_auth_name')) {
            $this->set_cookiename($name);
        }
    }

    /**
     * Register session handler
     */
    public function register_session_handler()
    {
        if (session_id()) {
            // Session already exists, skip
            return;
        }

        ini_set('session.serialize_handler', 'php');

        // set custom functions for PHP session management
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'sess_write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );
    }

    /**
     * Wrapper for session_start()
     */
    public function start()
    {
        $this->start   = microtime(true);
        $this->ip      = rcube_utils::remote_addr();
        $this->logging = $this->config->get('session_debug', false);

        $lifetime = $this->config->get('session_lifetime', 1) * 60;
        $this->set_lifetime($lifetime);

        session_start();
    }

    /**
     * Abstract methods should be implemented by driver classes
     */
    abstract function open($save_path, $session_name);
    abstract function close();
    abstract function destroy($key);
    abstract function read($key);
    abstract function write($key, $vars);
    abstract function update($key, $newvars, $oldvars);

    /**
     * Session write handler. This calls the implementation methods for write/update after some initial checks.
     *
     * @param string $key  Session identifier
     * @param string $vars Serialized data string
     *
     * @return bool True on success, False on failure
     */
    public function sess_write($key, $vars)
    {
        if ($this->nowrite) {
            return true;
        }

        // check cache
        $oldvars = $this->get_cache($key);

        // if there are cached vars, update store, else insert new data
        if ($oldvars) {
            $newvars = $this->_fixvars($vars, $oldvars);
            return $this->update($key, $newvars, $oldvars);
        }
        else {
            return $this->write($key, $vars);
        }
    }

    /**
     * Wrapper for session_write_close()
     */
    public function write_close()
    {
        session_write_close();

        // write_close() is called on script shutdown, see rcube::shutdown()
        // execute cleanup functionality if enabled by session gc handler
        // we do this after closing the session for better performance
        $this->gc_shutdown();
    }

    /**
     * Creates a new (separate) session
     *
     * @param array $data Session data
     *
     * @return string Session identifier (on success)
     */
    public function create($data)
    {
        $length = strlen(session_id());
        $key    = rcube_utils::random_bytes($length);

        // create new session
        if ($this->write($key, $this->serialize($data))) {
            return $key;
        }
    }

    /**
     * Merge vars with old vars and apply unsets
     */
    protected function _fixvars($vars, $oldvars)
    {
        $newvars = '';

        if ($oldvars !== null) {
            $a_oldvars = $this->unserialize($oldvars);

            if (is_array($a_oldvars)) {
                // remove unset keys on oldvars
                foreach ((array)$this->unsets as $var) {
                    if (isset($a_oldvars[$var])) {
                        unset($a_oldvars[$var]);
                    }
                    else {
                        $path = explode('.', $var);
                        $k = array_pop($path);
                        $node = &$this->get_node($path, $a_oldvars);
                        unset($node[$k]);
                    }
                }

                $newvars = $this->serialize(array_merge(
                    (array)$a_oldvars, (array)$this->unserialize($vars)));
            }
            else {
                $newvars = $vars;
            }
        }

        $this->unsets = [];

        return $newvars;
    }

    /**
     * Execute registered garbage collector routines
     *
     * @param int $maxlifetime Maximum session lifetime
     *
     * @return bool True on success, False on failure
     */
    public function gc($maxlifetime)
    {
        // move gc execution to the script shutdown function
        // see rcube::shutdown() and rcube_session::write_close()
        $this->gc_enabled = $maxlifetime;

        return true;
    }

    /**
     * Register additional garbage collector functions
     *
     * @param mixed $func Callback function
     */
    public function register_gc_handler($func)
    {
        foreach ($this->gc_handlers as $handler) {
            if ($handler == $func) {
                return;
            }
        }

        $this->gc_handlers[] = $func;
    }

    /**
     * Garbage collector handler to run on script shutdown
     */
    protected function gc_shutdown()
    {
        if ($this->gc_enabled) {
            foreach ($this->gc_handlers as $fct) {
                call_user_func($fct);
            }
        }
    }

    /**
     * Generate and set new session id
     *
     * @param bool $destroy If enabled the current session will be destroyed
     *
     * @return bool True on success, False on failure
     */
    public function regenerate_id($destroy = true)
    {
        $old_id = session_id();

        // Since PHP 7.0 session_regenerate_id() will cause the old
        // session data update, we don't need this
        $this->ignore_write = true;
        session_regenerate_id($destroy);
        $this->ignore_write = false;

        $this->vars = null;
        $this->key  = session_id();

        $this->log("Session regenerate: $old_id -> {$this->key}");

        return true;
    }

    /**
     * See if we have vars of this key already cached, and if so, return them.
     *
     * @param string $key Session identifier
     *
     * @return string Serialized data string
     */
    protected function get_cache($key)
    {
        // no session data in cache (read() returns false)
        if (!$this->key) {
            $cache = null;
        }
        // use internal data for fast requests (up to 0.5 sec.)
        else if ($key == $this->key && (!$this->vars || microtime(true) - $this->start < 0.5)) {
            $cache = $this->vars;
        }
        else { // else read data again
            $cache = $this->read($key);
        }

        return $cache;
    }

    /**
     * Append the given value to the certain node in the session data array
     *
     * Warning: Do not use if you already modified $_SESSION in the same request (#1490608)
     *
     * @param string $path  Path denoting the session variable where to append the value
     * @param string $key   Key name under which to append the new value (use null for appending to an indexed list)
     * @param mixed  $value Value to append to the session data array
     */
    public function append($path, $key, $value)
    {
        // re-read session data from DB because it might be outdated
        if (!$this->reloaded && microtime(true) - $this->start > 0.5) {
            $this->reload();
            $this->reloaded = true;
            $this->start = microtime(true);
        }

        $node = &$this->get_node(explode('.', $path), $_SESSION);

        if ($key !== null) {
            $node[$key] = $value;
            $path .= '.' . $key;
        }
        else {
            $node[] = $value;
        }

        $this->appends[] = $path;

        // when overwriting a previously unset variable
        if (array_key_exists($path, $this->unsets)) {
            unset($this->unsets[$path]);
        }
    }

    /**
     * Unset a session variable
     *
     * @param string $var Variable name (can be a path denoting a certain node
     *                    in the session array, e.g. compose.attachments.5)
     *
     * @return bool True on success, False on failure
     */
    public function remove($var = null)
    {
        if (empty($var)) {
            return $this->destroy(session_id());
        }

        $this->unsets[] = $var;

        if (isset($_SESSION[$var])) {
            unset($_SESSION[$var]);
        }
        else {
            $path = explode('.', $var);
            $key = array_pop($path);
            $node = &$this->get_node($path, $_SESSION);
            unset($node[$key]);
        }

        return true;
    }

    /**
     * Kill this session
     */
    public function kill()
    {
        $this->log("Session destroy: " . session_id());

        $this->vars = null;
        $this->ip   = rcube_utils::remote_addr(); // update IP (might have changed)
        $this->destroy(session_id());

        rcube_utils::setcookie($this->cookiename, '-del-', time() - 60);
    }

    /**
     * Re-read session data from storage backend
     */
    public function reload()
    {
        // collect updated data from previous appends
        $merge_data = [];
        foreach ((array) $this->appends as $var) {
            $path = explode('.', $var);
            $value = $this->get_node($path, $_SESSION);
            $k = array_pop($path);
            $node = &$this->get_node($path, $merge_data);
            $node[$k] = $value;
        }

        if ($this->key) {
            $data = $this->read($this->key);
        }

        if (!empty($data)) {
            session_decode($data);

            // apply appends and unsets to reloaded data
            $_SESSION = array_merge_recursive($_SESSION, $merge_data);

            foreach ((array) $this->unsets as $var) {
                if (isset($_SESSION[$var])) {
                    unset($_SESSION[$var]);
                }
                else {
                    $path = explode('.', $var);
                    $k = array_pop($path);
                    $node = &$this->get_node($path, $_SESSION);
                    unset($node[$k]);
                }
            }
        }
    }

    /**
     * Returns a reference to the node in data array referenced by the given path.
     * e.g. ['compose','attachments'] will return $_SESSION['compose']['attachments']
     */
    protected function &get_node($path, &$data_arr)
    {
        $node = &$data_arr;

        if (!empty($path)) {
            foreach ((array) $path as $key) {
                if (!isset($node[$key])) {
                    $node[$key] = [];
                }
                $node = &$node[$key];
            }
        }

        return $node;
    }

    /**
     * Serialize session data
     */
    protected function serialize($vars)
    {
        $data = '';

        if (is_array($vars)) {
            foreach ($vars as $var => $value)
                $data .= $var.'|'.serialize($value);
        }
        else {
            $data = 'b:0;';
        }

        return $data;
    }

    /**
     * Unserialize session data
     * https://www.php.net/manual/en/function.session-decode.php#56106
     *
     * @param string $str Serialized data string
     *
     * @return array Unserialized data
     */
    public static function unserialize($str)
    {
        $str    = (string) $str;
        $endptr = strlen($str);
        $p      = 0;

        $serialized = '';
        $items      = 0;
        $level      = 0;

        while ($p < $endptr) {
            $q = $p;
            while ($str[$q] != '|')
                if (++$q >= $endptr)
                    break 2;

            if ($str[$p] == '!') {
                $p++;
                $has_value = false;
            }
            else {
                $has_value = true;
            }

            $name = substr($str, $p, $q - $p);
            $q++;

            $serialized .= 's:' . strlen($name) . ':"' . $name . '";';

            if ($has_value) {
                for (;;) {
                    $p = $q;
                    switch (strtolower($str[$q])) {
                    case 'n': // null
                    case 'b': // boolean
                    case 'i': // integer
                    case 'd': // decimal
                        do $q++;
                        while (($q < $endptr) && ($str[$q] != ';'));
                        $q++;
                        $serialized .= substr($str, $p, $q - $p);
                        if ($level == 0) {
                            break 2;
                        }
                        break;
                    case 'r': // reference
                        $q+= 2;
                        for ($id = ''; ($q < $endptr) && ($str[$q] != ';'); $q++) {
                            $id .= $str[$q];
                        }
                        $q++;
                        // increment pointer because of outer array
                        $serialized .= 'R:' . ($id + 1) . ';';
                        if ($level == 0) {
                            break 2;
                        }
                        break;
                    case 's': // string
                        $q+=2;
                        for ($length=''; ($q < $endptr) && ($str[$q] != ':'); $q++) {
                            $length .= $str[$q];
                        }
                        $q+=2;
                        $q+= (int)$length + 2;
                        $serialized .= substr($str, $p, $q - $p);
                        if ($level == 0) {
                            break 2;
                        }
                        break;
                    case 'a': // array
                    case 'o': // object
                        do $q++;
                        while ($q < $endptr && $str[$q] != '{');
                        $q++;
                        $level++;
                        $serialized .= substr($str, $p, $q - $p);
                        break;
                    case '}': // end of array|object
                        $q++;
                        $serialized .= substr($str, $p, $q - $p);
                        if (--$level == 0) {
                            break 2;
                        }
                        break;
                    default:
                        return false;
                    }
                }
            }
            else {
                $serialized .= 'N;';
                $q += 2;
            }
            $items++;
            $p = $q;
        }

        return unserialize('a:' . $items . ':{' . $serialized . '}');
    }

    /**
     * Setter for session lifetime
     *
     * @param int $lifetime Session lifetime (in seconds)
     */
    public function set_lifetime($lifetime)
    {
        $this->lifetime = max(120, $lifetime);

        // valid time range is now - 1/2 lifetime to now + 1/2 lifetime
        $now = time();
        $this->now = $now - ($now % ($this->lifetime / 2));
    }

    /**
     * Getter for remote IP saved with this session
     *
     * @return string Client IP address
     */
    public function get_ip()
    {
        return $this->ip;
    }

    /**
     * Setter for cookie encryption secret
     *
     * @param string $secret Authentication secret string
     */
    public function set_secret($secret = null)
    {
        // generate random hash and store in session
        if (!$secret) {
            if (!empty($_SESSION['auth_secret'])) {
                $secret = $_SESSION['auth_secret'];
            }
            else {
                $secret = rcube_utils::random_bytes(strlen($this->key));
            }
        }

        $_SESSION['auth_secret'] = $secret;
    }

    /**
     * Enable/disable IP check
     *
     * @param bool $check IP address checking state
     */
    public function set_ip_check($check)
    {
        $this->ip_check = $check;
    }

    /**
     * Setter for the cookie name used for session cookie
     *
     * @param string $name Authentication cookie name
     */
    public function set_cookiename($name)
    {
        if ($name) {
            $this->cookiename = $name;
        }
    }

    /**
     * Check session authentication cookie
     *
     * @return bool True if valid, False if not
     */
    public function check_auth()
    {
        $this->cookie = isset($_COOKIE[$this->cookiename]) ? $_COOKIE[$this->cookiename] : null;

        $result = $this->ip_check ? rcube_utils::remote_addr() == $this->ip : true;
        $prev   = null;

        if (!$result) {
            $this->log("IP check failed for " . $this->key . "; expected " . $this->ip . "; got " . rcube_utils::remote_addr());
        }

        if ($result && $this->_mkcookie($this->now) != $this->cookie) {
            $this->log("Session auth check failed for " . $this->key . "; timeslot = " . date('Y-m-d H:i:s', $this->now));
            $result = false;

            // Check if using id from a previous time slot
            for ($i = 1; $i <= 2; $i++) {
                $prev = $this->now - ($this->lifetime / 2) * $i;
                if ($this->_mkcookie($prev) == $this->cookie) {
                    $this->log("Send new auth cookie for " . $this->key . ": " . $this->cookie);
                    $this->set_auth_cookie();
                    $result = true;
                }
            }
        }

        if (!$result) {
            $this->log("Session authentication failed for " . $this->key
                . "; invalid auth cookie sent; timeslot = " . date('Y-m-d H:i:s', $prev));
        }

        return $result;
    }

    /**
     * Set session authentication cookie
     */
    public function set_auth_cookie()
    {
        $this->cookie = $this->_mkcookie($this->now);
        rcube_utils::setcookie($this->cookiename, $this->cookie, 0);
        $_COOKIE[$this->cookiename] = $this->cookie;
    }

    /**
     * Create session cookie for specified time slot.
     *
     * @param int $timeslot Time slot to use
     *
     * @return string Cookie value
     */
    protected function _mkcookie($timeslot)
    {
        // make sure the secret key exists
        $this->set_secret();

        // no need to hash this, it's just a random string
        return $_SESSION['auth_secret'] . '-' . $timeslot;
    }

    /**
     * Writes debug information to the log
     *
     * @param string $line Log line
     */
    function log($line)
    {
        if ($this->logging) {
            rcube::write_log('session', $line);
        }
    }
}
