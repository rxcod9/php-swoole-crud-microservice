<?php

namespace App\Core;

use App\Core\Container;
use App\Tasks\TaskInterface;
use RuntimeException;
use Swoole\Server\Task;

/**
 * Class TaskDispatcher
 *
 * Resolves and invokes task classs using a dependency injection container.
 *
 * @package App\Core
 */
final class TaskDispatcher
{
    /**
     * TaskDispatcher constructor.
     *
     * @param Container $c Dependency Injection Container
     */
    public function __construct(
        private Container $c
    ) {
        //
    }

    /**
     * Dispatch a task class.
     *
     * Resolves the task and method from the class string,
     * injects the task if supported, and invokes the method with parameters.
     *
     * @param string $class Action in the format 'Task@method'
     * @param array $arguments Parameters to pass to the method
     * @param Task $task Request object
     * @return bool Response from the task method
     *
     * @throws \InvalidArgumentException If the class format is invalid
     * @throws \RuntimeException If the task or handle method does not exist
     */
    public function dispatch(string $class, array $arguments, Task $task): bool
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Task class $class does not exist.");
        }

        // check interface
        if (!(new \ReflectionClass($class))->implementsInterface(TaskInterface::class)) {
            // var_export($class instanceof TaskInterface);
            // var_export(class_implements($class));
            // var_export((new \ReflectionClass($class))->implementsInterface(TaskInterface::class));
            throw new RuntimeException("Implement TaskInterface in your Task class $class.");
        }

        $instance = $this->c->get($class);

        try {
            $result = $this->handle($instance, $arguments);

            $task->finish([
                'class'     => $class,
                'arguments' => $arguments,
                'result'    => $result,
                'error'     => null
            ]);

            return true;
        } catch (\Throwable $e) {
            if (method_exists($instance, 'error')) {
                $result = $instance->error($e, ...$arguments);

                $task->finish([
                    'class'     => $class,
                    'arguments' => $arguments,
                    'result'    => $result,
                    'error'     => null
                ]);
                return true;
            }

            $task->finish([
                'class'     => $class,
                'arguments' => $arguments,
                'result'    => null,
                'error'     => $e->getMessage()
            ]);

            return false;
        }
    }

    private function handle($instance, $arguments)
    {
        if (method_exists($instance, 'handle')) {
            return $instance->handle(...$arguments);
        }

        if (is_callable($instance)) {
            // covers __invoke()
            return $instance(...$arguments);
        }

        throw new RuntimeException("Task " . get_class($instance) .  " must have a handle() method or be invokable (__invoke)");
    }
}
