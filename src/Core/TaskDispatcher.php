<?php

/**
 * src/Core/TaskDispatcher.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/TaskDispatcher.php
 */
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\TaskContractViolationException;
use App\Exceptions\TaskNotFoundException;
use App\Tasks\TaskInterface;
use ReflectionClass;
use Swoole\Server\Task;
use Throwable;

/**
 * Class TaskDispatcher
 * Resolves and invokes task classs using a dependency injection container.
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class TaskDispatcher
{
    /**
     * TaskDispatcher constructor.
     *
     * @param Container $container Dependency Injection Container
     */
    public function __construct(
        private Container $container
    ) {
        //
    }

    /**
     * Dispatch a task class.
     *
     * Resolves the task and method from the class string,
     * injects the task if supported, and invokes the method with parameters.
     *
     * @param string            $class     Action in the format 'Task@method'
     * @param array<int, mixed> $arguments Parameters to pass to the method
     * @param Task              $task      Request object
     *
     * @throws InvalidArgumentException If the class format is invalid
     * @throws RuntimeException         If the task or handle method does not exist
     *
     * @return bool Response from the task method
     */
    public function dispatch(string $class, array $arguments, Task $task): bool
    {
        if (!class_exists($class)) {
            throw new TaskNotFoundException(sprintf('Task class %s does not exist.', $class));
        }

        // check interface

        $reflectionClass = new ReflectionClass($class);
        if (!$reflectionClass->implementsInterface(TaskInterface::class)) {
            throw new TaskContractViolationException(sprintf('Implement TaskInterface in your Task class %s.', $class));
        }

        $instance = $this->container->get($class);

        try {
            $result = $this->handle($instance, $arguments);

            $task->finish([
                'class'     => $class,
                'arguments' => $arguments,
                'result'    => $result,
            ]);

            return true;
        } catch (Throwable $throwable) {
            if (method_exists($instance, 'error')) {
                $result = $instance->error($throwable, ...$arguments);

                $task->finish([
                    'class'     => $class,
                    'arguments' => $arguments,
                    'result'    => $result,
                ]);
                return true;
            }

            error_log('Exception: ' . $throwable->getMessage()); // logged internally

            $task->finish([
                'class'     => $class,
                'arguments' => $arguments,
                'error'     => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    private function handle($instance, array $arguments)
    {
        if (method_exists($instance, 'handle')) {
            return $instance->handle(...$arguments);
        }

        if (is_callable($instance)) {
            // covers __invoke()
            return $instance(...$arguments);
        }

        throw new TaskContractViolationException('Task ' . $instance::class . ' must have a handle() method or be invokable (__invoke)');
    }
}
