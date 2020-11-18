<?php


namespace DI\AOP;



trait ProxyTrait
{
    /**
     * AOP proxy call method
     *
     * @param string $class
     * @param string $method
     * @param array $params
     * @param \Closure $closure
     * @return mixed|null
     */
    public static function __proxyCall(string $class, string $method, array $params, \Closure $closure)
    {
        $proceedingJoinPoint = new ProceedingJoinPoint($class, $method, $params, $closure);
        return self::handleAround($proceedingJoinPoint);
    }

    public static function handleAround(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $aspects = AspectCollector::getAspect($proceedingJoinPoint->className, $proceedingJoinPoint->method);

        $pipeline = new AspectPipeline();

        return $pipeline->via('process')
            ->send($proceedingJoinPoint)
            ->through($aspects)
            ->then(function (ProceedingJoinPoint $proceedingJoinPoint) {
                return $proceedingJoinPoint->processOriginalMethod();
            });
    }
}