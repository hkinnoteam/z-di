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

    protected $proxies = [];

    protected $classMap = [];

    protected $annotations = [];

    protected $finder;

    protected $aspects;

    protected static $instance;

    public function __construct(string $dir, string $config)
    {
        $this->dir    = $dir;
        $this->config = $config;
        $this->finder = new Finder();
        $this->finder->files()->in($this->dir);
        $this->generateProxyFile();
    }


    public function generateProxyFile(): void
    {
        $this->collectMethodMapFile();
        $this->collectAnnotationMapFile();
        $this->collectClassNameByAnnotation();
        $this->generateFiles();
    }

    protected function generateFiles()
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $namespaces = array_keys($this->proxies);
        foreach ($namespaces as $class){
            $file = ProxyClassLoader::getLoader()->findFile($class);
            $code = file_get_contents($file);
            $ast = $parser->parse($code);
            $visitor = new ProxyVisitor($class);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $proxyAst = $traverser->traverse($ast);
            $printer = new Standard();
            $proxyCode = $printer->prettyPrintFile($proxyAst);
            file_put_contents($this->getProxyFilePath($visitor->getProxyClassName()), $proxyCode);
        }
    }
    
    public function collectClassNameByAnnotation()
    {
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
                            if (!isset($this->proxies[$className])) {
                                $this->proxies[$className] = $this->getProxyFilePath($className);
                            }
                            $annotationReflection = new \ReflectionClass($methodAnnotation);
                            $result = array_search($annotationReflection->getName(), $this->annotations, true);
                            if ($result){
                                AspectCollector::setAspect($className, $method->getName(), $result);
                            }
                        }
                    }
                }
            }
        }
    }

    public static function initClassReflector(array $paths): ClassReflector
    {
        $reflection = new BetterReflection();
        $astLocator = $reflection->astLocator();
        return new ClassReflector(new AggregateSourceLocator([
            new DirectoriesSourceLocator($paths, $astLocator)
        ]));
    }


    public function collectMethodMapFile(): void
    {
        $this->aspects = include $this->config . '/aspects.php';
        foreach ($this->aspects as $aspect) {
            $classes  = new \ReflectionClass($aspect);
            $property = $classes->getProperty('classes')->getValue(new $aspect);
            foreach ($property as $v) {
                [$target, $method] = explode('::', $v);
                AspectCollector::setAspect($target, $method, $aspect);
                if (!isset($this->proxies[$target])) {
                    $this->proxies[$target] = $this->getProxyFilePath($target);
                }
            }
        }
    }

    public function collectAnnotationMapFile(): void
    {
        foreach ($this->aspects as $aspect) {
            $result = $this->getAnnotations($aspect);
            foreach ($result as $annotation) {
                if (!in_array($annotation, $this->annotations, true)) {
                    $this->annotations [$aspect] = $annotation;
                }
            }
        }
    }

    protected function getAnnotations($aspect): array
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

    protected function getClassName($className): string
    {
        return basename(str_replace('\\', '/', $className));
    }

    protected function getProxyFilePath($className)
    {
        return BASE_PATH . '/proxies/' . $this->getClassName($className) . '.php';
    }

    /**
     * @return array
     */
    public function getProxies(): array
    {
        return $this->proxies;
    }

}