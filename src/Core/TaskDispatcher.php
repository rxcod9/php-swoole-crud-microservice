<?php

/**
 * src/Core/TaskDispatcher.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
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
    public const TAG = 'TaskDispatcher';

    /**
     * TaskDispatcher constructor.
     *
     * @param Container $container Dependency Injection Container
     */
    public function __construct(
        private Container $container
    ) {
        // Empty Constructor
    }

    /**
     * Dispatch a task class.
     *
     * Resolves the task and method from the class string,
     * injects the task if supported, and invokes the method with parameters.
     *
     * @param string            $class     Action in the format 'Task@method'
     * @param string $id Id
     * @param array<int, mixed> $arguments Parameters to pass to the method
     * @param Task              $task      Request object
     *
     * @throws TaskNotFoundException If the class format is invalid
     * @throws TaskContractViolationException         If the task or handle method does not exist
     *
     * @return bool Response from the task method
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    /**
     * Dispatch a task class.
     *
     * @param string $class     The fully qualified class name of the task.
     * @param string $id        Task identifier.
     * @param array<int, mixed> $arguments Parameters to pass to the handler.
     * @param Task   $task      The task instance being processed.
     *
     * @return bool True if successfully executed, false otherwise.
     * @throws TaskNotFoundException
     * @throws TaskContractViolationException
     */
    public function dispatch(
        string $class,
        string $id,
        array $arguments,
        Task $task
    ): bool {
        $instance = $this->resolveTask($class);

        try {
            $result = $this->handle($instance, $id, $arguments);
            $this->finalizeSuccess($task, $class, $id, $arguments, $result);
            return true;
        } catch (Throwable $throwable) {
            return $this->handleFailure($instance, $task, $class, $id, $arguments, $throwable);
        }
    }

    /**
     * Resolve a task instance from the container and validate it.
     */
    private function resolveTask(string $class): TaskInterface
    {
        if (!class_exists($class)) {
            throw new TaskNotFoundException(sprintf('Task class %s does not exist.', $class));
        }

        $reflectionClass = new ReflectionClass($class);
        if (!$reflectionClass->implementsInterface(TaskInterface::class)) {
            throw new TaskContractViolationException(
                sprintf('Implement TaskInterface in your Task class %s.', $class)
            );
        }

        return $this->container->get($class);
    }

    /**
     * Finalize successful task execution.
     *
     * @param array<int, mixed> $arguments Arguments
     */
    private function finalizeSuccess(
        Task $task,
        string $class,
        string $id,
        array $arguments,
        mixed $result
    ): void {
        $task->finish([
            'class'     => $class,
            'id'        => $id,
            'arguments' => $arguments,
            'result'    => $result,
        ]);
    }

    /**
     * Handle failure path — calls custom error() if available, else logs error.
     *
     * @param array<int, mixed> $arguments Arguments
     */
    private function handleFailure(
        object $instance,
        Task $task,
        string $class,
        string $id,
        array $arguments,
        Throwable $throwable
    ): bool {
        if (method_exists($instance, 'error')) {
            try {
                $result = $instance->error($throwable, $id, ...$arguments);
                $this->finalizeSuccess($task, $class, $id, $arguments, $result);
                return true;
            } catch (Throwable $throwable) {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage());

                $task->finish([
                    'class'     => $class,
                    'id'        => $id,
                    'arguments' => $arguments,
                    'error'     => $throwable->getMessage(),
                ]);
                return true;
            }
        }

        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage());

        $task->finish([
            'class'     => $class,
            'id'        => $id,
            'arguments' => $arguments,
            'error'     => $throwable->getMessage(),
        ]);

        return false;
    }

    /**
     * Invoke the handle method or __invoke() of the task instance.
     *
     * @param TaskInterface $task Task instance
     * @param string $id Id
     * @param array<int, mixed> $arguments Arguments to pass to the method
     *
     * @return mixed Response from the task method
     *
     * @throws TaskContractViolationException If the task does not have a handle method or is not invokable
     */
    private function handle(TaskInterface $task, string $id, array $arguments): mixed
    {
        return $task->handle($id, ...$arguments);
    }
}
