<?php


namespace DI\AOP;


use Composer\Autoload\ClassLoader;
use DI\Annotation\AnnotationInterface;
use DI\ProxyClassLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\Adapter\ReflectionMethod;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\EvaledCodeSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\MemoizingSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use Symfony\Component\Finder\Finder;

class ProxyGenerator
{
    protected $config;

    protected $dir;

    protected $proxyDir;

    protected $proxies = [];

    protected $annotations = [];

    protected $finder;

    protected $aspects;

    protected static $instance;

    public function __construct(string $dir, array $aspects)
    {
        $this->dir      = $dir;
        $this->aspects  = $aspects;
        $this->proxyDir = $dir . '/proxies';
        $this->finder   = new Finder();
        $this->finder->files()->in($this->dir);
        // cache not existing
        if (! file_exists($this->getProxyDir())) {
            $this->generateProxyFile();
        }else{
            //cache exist gen mapping array
            $this->generateProxiesMapping();
        }
    }

    public function generateProxiesMapping():void 
    {
        $reflectorClasses = self::initClassReflector([$this->getProxyDir()]);
        $class  = $reflectorClasses->getAllClasses();
        foreach ($class as $reflection){
            $className = $reflection->getName();
            $this->setProxies($className);
        }
    }
    
    public function generateProxyFile(): void
    {
        $this->collectMethodAspect();
        $this->collectAnnotationAspect();
        $this->generateFiles();
    }

    public function collectMethodAspect(): void
    {
        foreach ($this->aspects as $aspect) {
            $classes  = new \ReflectionClass($aspect);
            $property = $classes->getProperty('classes')->getValue(new $aspect);
            foreach ($property as $v) {
                [$className, $method] = explode('::', $v);
                AspectCollector::setAspect($className, $method, $aspect);
                $this->setProxies($className);
            }
        }
    }

    public function collectAnnotationAspect()
    {
        $this->collectAspectsAnnotations();
        $reader = new AnnotationReader();
        $res    = self::initClassReflector([$this->dir]);
        $class  = $res->getAllClasses();
        foreach ($class as $reflection) {
            $methods   = $reflection->getImmediateMethods();
            $className = $reflection->getName();
            foreach ($methods as $method) {
                $methodAnnotations = $reader->getMethodAnnotations(new ReflectionMethod($method));
                if (!empty($methodAnnotations)) {
                    foreach ($methodAnnotations as $methodAnnotation) {
                        if ($methodAnnotation instanceof AnnotationInterface) {
                            $this->setProxies($className);
                            $annotationReflection = new \ReflectionClass($methodAnnotation);
                            $aspect = array_search($annotationReflection->getName(), $this->annotations, true);
                            if ($aspect){
                                AspectCollector::setAspect($className, $method->getName(), $aspect);
                            }
                        }
                    }
                }
            }
        }
    }

    public function collectAspectsAnnotations(): void
    {
        foreach ($this->aspects as $aspect) {
            $result = $this->getAspectAnnotations($aspect);
            foreach ($result as $annotation) {
                if (!in_array($annotation, $this->annotations, true)) {
                    $this->annotations [$aspect] = $annotation;
                }
            }
        }
    }

    public function getAspectAnnotations($aspect): array
    {
        $annotations = [];
        $classes     = new \ReflectionClass($aspect);
        $property    = $classes->getProperty('annotations')->getValue(new $aspect);
        foreach ($property as $v) {
            if (!in_array($v, $annotations, true)) {
                $annotations[] = $v;
            }
        }
        return $annotations;
    }

    public function getProxyDir()
    {
        return $this->proxyDir;
    }

    public function getProxies(): array
    {
        return $this->proxies;
    }

    public static function initClassReflector(array $paths): ClassReflector
    {
        $reflection = new BetterReflection();
        $astLocator = $reflection->astLocator();

        if (!isset(self::$instance)){
            self::$instance = new ClassReflector(new AggregateSourceLocator([
                new DirectoriesSourceLocator($paths, $astLocator)
            ]));
            return self::$instance;
        }

        return self::$instance;
    }

    private function setProxies(string $class)
    {
        if (!isset($this->proxies[$class])) {
            $this->proxies[$class] = $this->getProxyFilePath($class);
        }
    }

    protected function generateFiles()
    {
        $ast = new Ast();

        if (! file_exists($this->getProxyDir())) {
            mkdir($this->getProxyDir(), 0755, true);
        }

        foreach ($this->proxies as $class => $proxyFile){
            $this->putFile($ast, $class);
        }
    }

    protected function putFile(Ast $ast, string $className)
    {
        $proxyFile = $this->getProxyFilePath($className);
        $modified = true;

        if(file_exists($proxyFile)){
            $modified = $this->isModified($className);
        }

        if ($modified){
            $code = $ast->putProxy($className);
            file_put_contents($this->getProxyFilePath($className), $code);
        }
    }

    protected function isModified(string $className): bool
    {
        $proxyFilePath = $this->getProxyFilePath($className);
        $time = $this->lastModified($proxyFilePath);
        $origin = ProxyClassLoader::getLoader()->findFile($className);
        if ($time >= $this->lastModified($origin)) {
            return false;
        }

        return true;
    }

    protected function lastModified(string $path): int
    {
        return filemtime($path);
    }

    protected function getClassName($className): string
    {
        return basename(str_replace('\\', '/', $className));
    }

    protected function getProxyFilePath($className)
    {
        return $this->dir . '/proxies/' . $this->getClassName($className) . '.php';
    }
}
