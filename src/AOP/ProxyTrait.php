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
        $mapParams = self::mapParameters($class, $method, $params);
        $proceedingJoinPoint = new ProceedingJoinPoint($class, $method, $mapParams, $closure);
        return self::handleAround($proceedingJoinPoint);
    }

    public static function mapParameters(string $class, string $method, array $params)
    {
        $mapParams = [];
        $relection = new \ReflectionMethod($class, $method);
        foreach ($relection->getParameters() as $index => $parameter){
            $mapParams[$parameter->name] = $params[$index] ?? null;
        }
        return $mapParams;
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