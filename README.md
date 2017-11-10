Static router
=============
Static alias router

Installation
------------
```sh
$ composer require geniv/nette-static-router
```
or
```json
"geniv/nette-static-router": ">=1.0.0"
```

require:
```json
"php": ">=5.6.0",
"nette/nette": ">=2.4.0",
"geniv/nette-locale": ">=1.0.0"
```

Include in application
----------------------
neon configure:
```neon
# static router
staticRouter:
#   autowired: self     # default self, true|false|self|null
#   domainSwitch: false
#   domainAlias:
#        example.cz: cs
#        example.com: en
#        example.de: de
    route:
        cs:
            "staticky-slug": "Homepage:pokus"
            "staticky-slug1": "Homepage:pokus2"
        en:
            "static-slu": "Homepage:pokus"
            "static-slug1": "Homepage:pokus2"
```

neon configure extension:
```neon
extensions:
    staticRouter: StaticRouter\Bridges\Nette\Extension
```

RouterFactory.php:
```php
public static function createRouter(Locale $locale, StaticRouter $staticRouter): IRouter
...
$router[] = $staticRouter;
$staticRouter->setDefaultParameters('Homepage', 'default', 'cs');
$staticRouter->setPaginatorVariable('visualPaginator-page');
//$staticRouter->setSecure(true);
//$staticRouter->setOneWay(true);
```
