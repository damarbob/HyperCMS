<?php

namespace App\Services;

class HyperHooks
{
    // Hold registered hooks
    protected $hooks = [];

    // Hold state information (optional)
    // This can be used to store temporary data or state between hooks
    protected $state = [];

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
     * Get all registered hooks (debugging purposes).
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    /**
     * Register a hook with a callback.
     */
    public function register(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hook][$priority][] = $callback;
        // Sort by priority (lower numbers run first)
        ksort($this->hooks[$hook]);
    }

    /**
     * Trigger a hook (action-like).
     */
    public function trigger(string $hook, array $params = [], bool $returnAll = false)
    {
        $output = [];
        if (isset($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    try {
                        $result = call_user_func_array($callback, $params);
                        if (!is_null($result)) {
                            $output[] = $result;
                        }
                    } catch (\Throwable $e) {
                        // Log or handle errors silently
                        error_log("Hook '$hook' error: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
        }

        // Return null if no output was collected.
        if (empty($output)) {
            return null;
        }

        // If only one output is available, return that value.
        if (count($output) === 1) {
            return $output[0];
        }

        return $returnAll ? $output : implode('', $output);
    }

    public function action(string $hook, array $params = [])
    {
        if (isset($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    try {
                        call_user_func_array($callback, $params);
                    } catch (\Throwable $e) {
                        // Log or handle errors silently
                        error_log("Hook '$hook' error: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Filter a value through callbacks (filter-like).
     */
    public function filter(string $hook, $value, array $params = [])
    {
        if (isset($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    try {
                        $value = call_user_func_array($callback, array_merge([$value], $params));
                    } catch (\Throwable $e) {
                        // Log to CodeIgniter's log for visibility
                        log_message('error', "Filter '$hook' error: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
        }
        return $value;
    }

    /**
     * Unregister a callback from a hook.
     */
    public function unregister(string $hook, callable $callback, int $priority = 10): bool
    {
        if (isset($this->hooks[$hook][$priority])) {
            $this->hooks[$hook][$priority] = array_filter(
                $this->hooks[$hook][$priority],
                fn($cb) => $cb !== $callback
            );
            return true;
        }
        return false;
    }

    /**
     * Clear all callbacks for a hook (optional helper).
     */
    public function clear(string $hook): void
    {
        unset($this->hooks[$hook]);
    }

    public function setState(string $key, $value): void
    {
        $this->state[$key] = $value;
    }

    public function getState(string $key)
    {
        return $this->state[$key] ?? null;
    }
}
