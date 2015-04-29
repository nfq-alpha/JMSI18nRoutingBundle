<?php

namespace JMS\I18nRoutingBundle\Router;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Routing\Route;

/**
 * The default strategy supports 3 different scenarios, and makes use of the
 * Symfony2 Translator Component.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DefaultPatternGenerationStrategy implements PatternGenerationStrategyInterface
{
    const STRATEGY_PREFIX = 'prefix';
    const STRATEGY_PREFIX_EXCEPT_DEFAULT = 'prefix_except_default';
    const STRATEGY_CUSTOM = 'custom';
    const STRATEGY_DOMAINS_PREFIX_EXCEPT_DEFAULT = 'domains_prefix_except_default';

    private $strategy;
    private $translator;
    private $translationDomain;
    private $locales;
    private $cacheDir;
    private $defaultLocale;
    private $localeMapping;
    private $domainMap;

    public function __construct(
        $strategy,
        TranslatorInterface $translator,
        array $locales,
        $cacheDir,
        $translationDomain = 'routes',
        $defaultLocale = 'en'
    ) {
        $this->strategy = $strategy;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->locales = $locales;
        $this->cacheDir = $cacheDir;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * {@inheritDoc}
     */
    public function generateI18nPatterns($routeName, Route $route)
    {
        if (self::STRATEGY_DOMAINS_PREFIX_EXCEPT_DEFAULT === $this->strategy) {
            $domainPatterns = array();

            foreach ($this->getDomainMap() as $domain => $domainParams) {
                $locales = isset($domainParams['locales'])
                    ? $domainParams['locales']
                    : $route->getOption('i18n_locales')
                        ?: $this->locales;

                foreach ($locales as $locale ) {
                    if ($routeName === $i18nPattern = $this->translator->trans($routeName, array(), $this->translationDomain,
                            $locale)
                    ) {
                        $i18nPattern = $route->getPattern();
                    }

                    $domainI18nPattern = $i18nPattern;
                    if ($locale !== $domainParams['default_locale']) {
                        if (array_key_exists($locale, $this->localeMapping)) {
                            $domainI18nPattern = '/' . $this->localeMapping[$locale] . $domainI18nPattern;
                        }
                    }

                    if (null !== $route->getOption('i18n_prefix')) {
                        $domainI18nPattern = $route->getOption('i18n_prefix') . $domainI18nPattern;
                    }

                    $domainPatterns[$domain][$domainI18nPattern][] = $locale;
                }
            }

            return $domainPatterns;
        }

        $patterns = array();

        foreach ($route->getOption('i18n_locales') ?: $this->locales as $locale) {
            // if no translation exists, we use the current pattern
            if ($routeName === $i18nPattern = $this->translator->trans($routeName, array(), $this->translationDomain,
                    $locale)
            ) {
                $i18nPattern = $route->getPattern();
            }

            // prefix with locale if requested
            if (self::STRATEGY_PREFIX === $this->strategy
                || (self::STRATEGY_PREFIX_EXCEPT_DEFAULT === $this->strategy && $this->defaultLocale !== $locale)
            ) {
                $i18nPattern = '/' . $locale . $i18nPattern;
                if (null !== $route->getOption('i18n_prefix')) {
                    $i18nPattern = $route->getOption('i18n_prefix') . $i18nPattern;
                }
            }

            $patterns[$i18nPattern][] = $locale;

        }

        return $patterns;
    }

    /**
     * {@inheritDoc}
     */
    public function addResources(RouteCollection $i18nCollection)
    {
        foreach ($this->locales as $locale) {
            if (file_exists($metadata = $this->cacheDir . '/translations/catalogue.' . $locale . '.php.meta')) {
                foreach (unserialize(file_get_contents($metadata)) as $resource) {
                    $i18nCollection->addResource($resource);
                }
            }
        }
    }

    /**
     * @return mixed
     */
    public function getLocaleMapping()
    {
        return $this->localeMapping;
    }

    /**
     * @param mixed $localeMapping
     */
    public function setLocaleMapping($localeMapping)
    {
        $this->localeMapping = $localeMapping;
    }

    /**
     * @return mixed
     */
    public function getDomainMap()
    {
        return $this->domainMap;
    }

    /**
     * @param mixed $domainMap
     */
    public function setDomainMap($domainMap)
    {
        $this->domainMap = $domainMap;
    }
}