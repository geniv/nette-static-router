<?php

namespace StaticRouter\Bridges\Nette;

use Nette\DI\CompilerExtension;
use StaticRouter\StaticRouter;


/**
 * Class Extension
 *
 * @author  geniv
 * @package StaticRouter\Bridges\Nette
 */
class Extension extends CompilerExtension
{
    /** @var array default values */
    private $defaults = [
        'autowired'    => 'self',
        'domainSwitch' => false,
        'domainAlias'  => [],
        'route'        => [],
    ];


    /**
     * Load configuration.
     */
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();
        $config = $this->validateConfig($this->defaults);

        // define router
        $builder->addDefinition($this->prefix('default'))
            ->setFactory(StaticRouter::class, [$config])
            ->setAutowired($config['autowired']);
    }
}
