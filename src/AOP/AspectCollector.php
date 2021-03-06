<?php


namespace DI\AOP;


class AspectCollector
{

    public static $aspects;

    public static $cacheName = 'aspect.cache';

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

    public static function serializeAspects(string $cachePath)
    {
        if (!empty(self::$aspects)) {
            if (!file_exists($cachePath)) {
                mkdir($cachePath, 0755, true);
            }

            $serializeAspects = serialize(self::$aspects);
            file_put_contents($cachePath . '/' . self::$cacheName, $serializeAspects);
        }
    }

    public static function unserializeAspects(string $cachePath)
    {
        $cacheFile = $cachePath . '/' .self::$cacheName;
        if (is_string($cachePath) && file_exists($cacheFile)){
            $fileContent = file_get_contents($cacheFile);
            self::$aspects = unserialize($fileContent, ["allowed_classes" => true]);
            return true;
        }
        return false;
    }
}
