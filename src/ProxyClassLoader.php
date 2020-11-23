<?php


namespace DI;


use Composer\Autoload\ClassLoader;
use DI\AOP\ProxyGenerator;
use Doctrine\Common\Annotations\AnnotationRegistry;

class ProxyClassLoader
{

    protected $composerClassLoader;

    protected $dir;
    
    protected $config;
    
    protected $proxies = [];

    public function __construct(ClassLoader $composerClassLoader, string $appPath, string $configPath)
    {
        $this->composerClassLoader = $composerClassLoader;
        $this->dir = $appPath;
        $this->config = $configPath;
        $aspects = include $this->config;
        $proxyGenerator = new ProxyGenerator($this->dir, $aspects);
        $this->proxies = $proxyGenerator->getProxies();
    }

    public static function init(string $appPath, string $configPath)
    {
        $loader = spl_autoload_functions();

        foreach ($loader as &$load){
            $unregisterLoader = $load;
            if (is_array($load) && $load[0] instanceof ClassLoader) {
                $composerClassLoader = $load[0];
                AnnotationRegistry::registerLoader(function ($class) use ($composerClassLoader) {
                    return (bool) $composerClassLoader->findFile($class);
                });
                $load[0] = new static($composerClassLoader);
            }

            spl_autoload_unregister($unregisterLoader);
        }

        unset($load);

        foreach ($loader as $load){
            spl_autoload_register($load);
        }
    }

    public function loadClass($className){
        if (isset($this->proxies[$className]) && file_exists($this->proxies[$className])){
            $file = $this->proxies[$className];
        }else{
            $file = $this->composerClassLoader->findFile($className);
        }
        if (is_string($file)){
            include $file;
        }
    }

    public static function getLoader():ClassLoader
    {
        $composerClass = '';
        foreach (get_declared_classes() as $declaredClass) {
            if (strpos($declaredClass, 'ComposerAutoloaderInit') === 0 && method_exists($declaredClass, 'getLoader')) {
                $composerClass = $declaredClass;
                break;
            }
        }
        return $composerClass::getLoader();
    }
}