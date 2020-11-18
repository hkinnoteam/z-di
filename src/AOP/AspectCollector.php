<?php


namespace DI\AOP;


class AspectCollector
{

    public static $aspects;

    public static function setAspect($class, $method, $aspect)
    {
        if (!isset(self::$aspects[$class][$method])){
            self::$aspects[$class][$method][] = $aspect;
        }
    }

    public static function getAspect($class, $method)
    {
        return self::$aspects[$class][$method] ?? [];
    }
    
    public static function getAllAspects()
    {
        return self::$aspects;
    }
}