<?php

use DI\ApplicationContext;

if (!function_exists('di')) {
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @template T
     * @psalm-param class-string<T> $id
     * @psalm-return T
     * @psalm-suppress InvalidReturnStatement
     *
     * @param null|mixed $id
     * @return mixed|\Psr\Container\ContainerInterface
     */
    function di($id = null)
    {
        $container = ApplicationContext::getContainer();
        if ($id) {
            return $container->get($id);
        }
        return $container;
    }
}
