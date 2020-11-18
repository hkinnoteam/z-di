<?php


namespace DI;


class ApplicationContext
{

    /**
     * @var Container
     */
    protected static $container;

    /**
     * @return Container
     */
    public static function getContainer(): Container
    {
        return self::$container;
    }

    public static function run():Container
    {
        if (empty(self::$container)){
            $builder = new ContainerBuilder();
            $builder->useAnnotations(true);
            self::$container = $builder->build();
        }
        return self::$container;
    }
}