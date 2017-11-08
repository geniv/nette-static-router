<?php

/**
 * Class StaticRouter
 *
 * @author  geniv
 * @package NetteWeb
 */
class StaticRouter implements \Nette\Application\IRouter
{
    use Nette\SmartObject;

    private $metadata, $flags;
    /** @var BaseLanguageService */
    private $language;
    private $slugs = null;


    /**
     * StaticRouter constructor.
     * @param \Nette\DI\Container $context
     * @param $slugs
     * @param array $metadata
     * @param int $flags
     */
    public function __construct(\Nette\DI\Container $context, $slugs, $metadata = [], $flags = 0)
    {
        $this->slugs = $slugs;

        $this->language = $context->getByType(\BaseLanguageService::class);

        $this->metadata = $metadata;
        $this->flags = $flags;
    }


    /**
     * Maps HTTP request to a Request object.
     * @param \Nette\Http\IRequest $httpRequest
     * @return \Nette\Application\Request|null
     */
    public function match(Nette\Http\IRequest $httpRequest)
    {
        $slug = $httpRequest->getUrl()->getPathInfo();
        $presenter = null;
        $parameters = [];

        // vlozeni defaultnich hodnot pri prazdne adrese
        if (!$slug && $this->metadata) {
            $parameters = $this->metadata;  // vlozeni parametru z metadat
            // pokud je definovany presenter tak ho preda dal a promaze index
            if (isset($parameters['presenter'])) {
                $presenter = $parameters['presenter'];
                unset($parameters['presenter']);
            }
        }

        // separace jazyku a slugu
        $separate = \Nette\Utils\Strings::match($slug, '/(?<lang>[a-z]{2}\/)?(?<slug>[[:alnum:]\-\_\/]+)/');

        // separace jazyku a nastaveni do systemu
        $lang = $this->language->getMainLang();
        if (isset($separate['lang']) && $separate['lang']) {
            $lang = trim($separate['lang'], '/_');
        }
        $this->language->setLang($lang);

        // nacteni separovaneho slugu
        $separeSlug = (isset($separate['slug']) ? $separate['slug'] : null);

        if ($separeSlug) {
            if (isset($this->slugs[$lang][$separeSlug])) {
                $match = \Nette\Utils\Strings::match($this->slugs[$lang][$separeSlug], '/(?<presenter>[[:alnum:]]{2,})(:(?<action>[[:alnum:]]+))?/');

                // nastaveni presenteru
                $presenter = $match['presenter'];

                // parametry prenasene dal
                $parameters = [
                    'lang' => $lang,
                    'action' => (isset($match['action']) ? $match['action'] : null),
                ];
            } else {
                return null;
            }
        }

        // prevzeti externich parametru a slozeni parametur
        $parameters += $httpRequest->getQuery();

        // ochrana proti prazdnemu presenteru
        if (!$presenter) {
            return null;
        }

        return new Nette\Application\Request(
            $presenter,
            $httpRequest->getMethod(),
            $parameters,
            $httpRequest->getPost(),
            $httpRequest->getFiles(),
            [Nette\Application\Request::SECURED => $httpRequest->isSecured()]
        );
    }


    /**
     * Constructs absolute URL from Request object.
     * @param \Nette\Application\Request $appRequest
     * @param \Nette\Http\Url $refUrl
     * @return null|string
     */
    public function constructUrl(Nette\Application\Request $appRequest, \Nette\Http\Url $refUrl)
    {
        if ($this->flags & self::ONE_WAY) {
            return null;
        }

        $params = $appRequest->parameters;
        $adr = $appRequest->presenterName . ':' . (isset($params['action']) ? $params['action'] : '');

        $slug = null;
        $lang = isset($params['lang']) ? $params['lang'] : null;
        if (isset($this->slugs[$lang])) {
            $slug = array_search($adr, $this->slugs[$lang], true);
        }

        if ($slug) {
            // pokud je jiny jazyk nez hlavni predhazuje jazyk
            if ($lang != $this->language->getMainLang()) {
                $slug = $lang . '/' . $slug;
            }
            // vyhozeni parametru
            unset($params['lang'], $params['action']);

            $url = new Nette\Http\Url($refUrl->getBaseUrl() . $slug);
            $url->setScheme($this->flags & self::SECURED ? 'https' : 'http');
            $url->setQuery($params);
            return $url->getAbsoluteUrl();
        } else {
            return null;
        }
    }
}
