<?php

/**
 * src/Core/ChannelTaskDispatcher.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/ChannelTaskDispatcher.php
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
 * Class ChannelTaskDispatcher
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
final readonly class ChannelTaskDispatcher
{
    public const TAG = 'ChannelTaskDispatcher';

    /**
     * ChannelTaskDispatcher constructor.
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
     *
     * @throws TaskNotFoundException If the class format is invalid
     * @throws TaskContractViolationException         If the task or handle method does not exist
     *
     * @return mixed Response from the task method
     */
    public function dispatch(string $class, string $id, array $arguments): mixed
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
            return $this->handle($instance, $id, $arguments);
        } catch (Throwable $throwable) {
            if (method_exists($instance, 'error')) {
                return $instance->error($throwable, $id, ...$arguments);
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally

            throw $throwable;
        }
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
