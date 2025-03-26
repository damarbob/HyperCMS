<?php

namespace App\Libraries;

class HyperHooks
{
    // Hold registered hooks
    protected $hooks = [];
    
    // Single instance holder (singleton)
    protected static $instance;
    
    // Get the singleton instance
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }
    
    /**
     * Register a hook with a callback.
     *
     * @param string   $hook     The hook name (e.g., 'page_header').
     * @param callable $callback The callback function.
     * @param int      $priority An optional priority (default 10).
     */
    public function register(string $hook, callable $callback, int $priority = 10)
    {
        $this->hooks[$hook][$priority][] = $callback;
        // Sort by priority so lower numbers run first
        ksort($this->hooks[$hook]);
    }
    
    /**
     * Trigger a hook.
     *
     * @param string $hook  The hook name.
     * @param array  $params An array of parameters to pass to each callback.
     * @param bool   $returnAll If true, returns an array of all outputs; otherwise, concatenates them.
     *
     * @return mixed
     */
    public function trigger(string $hook, array $params = [], bool $returnAll = false)
    {
        $output = [];
        if (isset($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    // Call callback with the provided params.
                    $result = call_user_func_array($callback, $params);
                    // Only add output if non-null
                    if (!is_null($result)) {
                        $output[] = $result;
                    }
                }
            }
        }
        return $returnAll ? $output : implode('', $output);
    }
}
