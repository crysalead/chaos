<?php
namespace chaos\model;

use chaos\SourceException;

class Finders
{
    /**
     * Stores the finder closures
     *
     * @var array
     */
    protected $_finders = [];

    /**
     * Sets a finder.
     *
     * @param  string  $name    The finder name.
     * @param  Closure $closure The finder closure.
     */
    public function set($name, $closure)
    {
        $this->_finders[$name] = $closure;
    }

    /**
     * Gets a finder
     *
     * @param  string $name The finder name.
     * @return mixed        The finder closure or `null` if not found.
     */
    public function get($name)
    {
        if (isset($this->_finders[$name])) {
            return $this->_finders[$name];
        }
    }

    /**
     * Checks if a finder exist.
     *
     * @param  string $name The finder name.
     * @return boolean
     */
    public function exists($name)
    {
        return isset($this->_finders[$name]);
    }

    /**
     * Removes a finder.
     *
     * @param string $name The finder name.
     */
    public function remove($name)
    {
        unset($this->_finders[$name]);
    }

    /**
     * Removes all finders.
     *
     * @param string $name The finders name.
     */
    public function clear()
    {
        $this->_finders = [];
    }

    /**
     * Magic method to execute a finder.
     *
     * @param  string $name   The name of the finder to execute.
     * @param  array  $params The parameters to pass to the finder.
     */
    public function __call($name, $params)
    {
        if (!isset($this->_finders[$name])) {
            throw new SourceException("Unexisting finder `'{$name}'`.");
        }
        call_user_func_array($this->_finders[$name], $params);
    }
}
