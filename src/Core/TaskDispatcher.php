<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\TaskContractViolationException;
use App\Exceptions\TaskNotFoundException;
use App\Tasks\TaskInterface;

use function get_class;

use InvalidArgumentException;

use function is_callable;

use ReflectionClass;
use RuntimeException;
use Swoole\Server\Task;
use Throwable;

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
     * @param Container $app Dependency Injection Container
     */
    public function __construct(
        private Container $app
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
     * @throws InvalidArgumentException If the class format is invalid
     * @throws RuntimeException If the task or handle method does not exist
     */
    public function dispatch(string $class, array $arguments, Task $task): bool
    {
        if (!class_exists($class)) {
            throw new TaskNotFoundException("Task class $class does not exist.");
        }

        // check interface
        if (!new ReflectionClass($class)->implementsInterface(TaskInterface::class)) {
            throw new TaskContractViolationException("Implement TaskInterface in your Task class $class.");
        }

        $instance = $this->app->get($class);

        try {
            $result = $this->handle($instance, $arguments);

            $task->finish([
                'class'     => $class,
                'arguments' => $arguments,
                'result'    => $result,
            ]);

            return true;
        } catch (Throwable $e) {
            if (method_exists($instance, 'error')) {
                $result = $instance->error($e, ...$arguments);

                $task->finish([
                    'class'     => $class,
                    'arguments' => $arguments,
                    'result'    => $result,
                ]);
                return true;
            }

            error_log('Exception: ' . $e->getMessage()); // logged internally

            $task->finish([
                'class'     => $class,
                'arguments' => $arguments,
                'error'     => $e->getMessage(),
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

        throw new TaskContractViolationException('Task ' . get_class($instance) . ' must have a handle() method or be invokable (__invoke)');
    }
}
