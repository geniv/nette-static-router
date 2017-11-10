<?php declare(strict_types=1);

namespace StaticRouter;

use Exception;
use Locale\Locale;
use Nette\Application\IRouter;
use Nette\Application\Request;
use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\SmartObject;


/**
 * Class StaticRouter
 *
 * @author  geniv
 */
class StaticRouter implements IRouter
{
    use SmartObject;

    /** @var bool default inactive https */
    private $secure = false;
    /** @var bool default inactive one way router */
    private $oneWay = false;
    /** @var array default parameters */
    private $defaultParameters = [];
    /** @var string paginator variable */
    private $praginatorVariable = 'vp';
    /** @var array domain locale switch */
    private $domain = [];

    /** @var array */
    private $route = [];
    /** @var Locale */
    private $locale = null;


    /**
     * StaticRouter constructor.
     *
     * @param array  $parameters
     * @param Locale $locale
     * @throws Exception
     */
    public function __construct(array $parameters, Locale $locale)
    {
        // pokud jeden z parametru domainSwitch nebo domainAlias neexistuje
        if (isset($parameters['domainSwitch']) XOR isset($parameters['domainAlias'])) {
            throw new Exception('Domain switch or domain alias is not defined in configure! ([domainSwitch: true, domainAlias: [cs: example.cz]])');
        }

        // nacteni domain nastaveni
        if (isset($parameters['domainSwitch']) && isset($parameters['domainAlias'])) {
            $this->domain = [
                'switch' => $parameters['domainSwitch'],
                'alias'  => $parameters['domainAlias'],
            ];
        }

        $this->route = $parameters['route'];
        $this->locale = $locale;
    }


    /**
     * Enable https, defalt is disable.
     *
     * @param $secure
     * @return StaticRouter
     */
    public function setSecure(bool $secure): self
    {
        $this->secure = $secure;
        return $this;
    }


    /**
     * Enable one way router.
     *
     * @param bool $oneWay
     * @return StaticRouter
     */
    public function setOneWay(bool $oneWay): self
    {
        $this->oneWay = $oneWay;
        return $this;
    }


    /**
     * Set default parameters, presenter, action and locale.
     *
     * @param string $presenter
     * @param string $action
     * @param string $locale
     * @return StaticRouter
     */
    public function setDefaultParameters(string $presenter, string $action, string $locale): self
    {
        $this->defaultParameters = [
            'presenter' => $presenter,
            'action'    => $action,
            'locale'    => $locale,
        ];
        return $this;
    }


    /**
     * Set paginator variable.
     *
     * @param string $variable
     * @return StaticRouter
     */
    public function setPaginatorVariable(string $variable): self
    {
        $this->praginatorVariable = $variable;
        return $this;
    }


    /**
     * Get array url domains.
     *
     * Use in AliasRouter::match().
     *
     * @return array
     */
    public function getDomain(): array
    {
        return $this->domain;
    }


    /**
     * Get locale code, empty code for default locale.
     *
     * Use in AliasRouter::constructUrl().
     *
     * @param $parameters
     * @return string
     */
    public function getCodeLocale(array $parameters): string
    {
        // null locale => empty locale in url
        if (!isset($parameters['locale'])) {
            return '';
        }

        // nuluje lokalizaci pri hlavnim jazyku a domain switch
        if (isset($parameters['locale']) && $parameters['locale'] == $this->locale->getCodeDefault() || ($this->domain && $this->domain['switch'])) {
            return '';
        }
        return $parameters['locale'];
    }


    /**
     * Maps HTTP request to a Request object.
     *
     * @param IRequest $httpRequest
     * @return Request|null
     */
    public function match(IRequest $httpRequest)
    {
        $pathInfo = $httpRequest->getUrl()->getPathInfo();

        // parse locale
        $locale = $this->defaultParameters['locale'];
        if (preg_match('/((?<locale>[a-z]{2})\/)?/', $pathInfo, $m) && isset($m['locale'])) {
            $locale = trim($m['locale'], '/_');
            $pathInfo = trim(substr($pathInfo, strlen($m['locale'])), '/_');   // ocesani slugu
        }

        // vyber jazyka podle domeny
        $domain = $this->domain['alias'];
        if ($this->domain && $this->domain['switch']) {
            $host = $httpRequest->url->host;    // nacteni url hostu pro zvoleni jazyka
            if (isset($domain[$host])) {
                $locale = $domain[$host];
            }
        }

        // parse alias
        $alias = null;
        if (preg_match('/((?<alias>[a-z0-9-\/]+)(\/)?)?/', $pathInfo, $m) && isset($m['alias'])) {
            $alias = trim($m['alias'], '/_');
            $pathInfo = trim(substr($pathInfo, strlen($m['alias'])), '/_');   // ocesani jazyka od slugu
        }

        // parse paginator
        $parameters = [];
        if (preg_match('/((?<vp>[a-z0-9-]+)(\/)?)?/', $pathInfo, $m) && isset($m['vp'])) {
            $parameters[$this->praginatorVariable] = trim($m['vp'], '/_');
        }

        // set default presenter
        $presenter = $this->defaultParameters['presenter'];

        // set locale to parameters
        $parameters['locale'] = $locale;

        // akceptace adresy kde je na konci zbytecne lomitko, odebere posledni lomitko
        if ($alias) {
            $alias = rtrim($alias, '/_');
        }

        if ($alias) {
            if (isset($this->route[$locale])) {
                if (isset($this->route[$locale][$alias])) {
                    list($presenter, $action) = explode(':', $this->route[$locale][$alias]);
                    $parameters['action'] = $action;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        $parameters += $httpRequest->getQuery();

        if (!$presenter) {
            return null;
        }

        return new Request(
            $presenter,
            $httpRequest->getMethod(),
            $parameters,
            $httpRequest->getPost(),
            $httpRequest->getFiles(),
            [Request::SECURED => $httpRequest->isSecured()]
        );
    }


    /**
     * Constructs absolute URL from Request object.
     *
     * @param Request $appRequest
     * @param Url     $refUrl
     * @return null|string
     */
    public function constructUrl(Request $appRequest, Url $refUrl)
    {
        if ($this->oneWay) {
            return null;
        }

        $parameters = $appRequest->parameters;

        $locale = (isset($parameters['locale']) ? $parameters['locale'] : '');
        $presenterAction = $appRequest->presenterName . ':' . (isset($parameters['action']) ? $parameters['action'] : '');

        if (isset($this->route[$locale]) && ($row = array_search($presenterAction, $this->route[$locale], true)) != null) {
            $part = implode('/', array_filter([$this->getCodeLocale($parameters), $row]));
            $alias = trim(isset($parameters[$this->praginatorVariable]) ? implode('_', [$part, $parameters[$this->praginatorVariable]]) : $part, '/_');

            unset($parameters['locale'], $parameters['action'], $parameters['alias'], $parameters['id'], $parameters[$this->praginatorVariable]);

            // create url address
            $url = new Url($refUrl->getBaseUrl() . $alias);
            $url->setScheme($this->secure ? 'https' : 'http');
            $url->setQuery($parameters);
            return $url->getAbsoluteUrl();
        } else {
            // vyber jazyka podle domeny
            // pokud je aktivni detekce podle domeny tak preskakuje FORWARD metodu nebo Homepage presenter
            // jde o vyhazovani lokalizace na HP pri zapnutem domain switch
            if ($this->domain && $this->domain['switch'] && ($appRequest->method != 'FORWARD' || $appRequest->presenterName == 'Homepage')) {
                $url = new Url($refUrl->getBaseUrl());  // vytvari zakladni cestu bez parametru
                $url->setScheme($this->secure ? 'https' : 'http');
                return $url->getAbsoluteUrl();
            }
        }
        return null;
    }
}
