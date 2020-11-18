<?php


namespace DI\AOP;


use App\ProceedingJoinPoint;

abstract class AbstractAspect implements AspectInterface
{
    public $classes = [];

    public $annotations = [];
}