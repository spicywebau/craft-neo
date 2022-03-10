<?php

namespace benf\neo;

use benf\neo\elements\Block;
use Twig\Environment;
use Twig\Extension\AbstractExtension;

use Twig\TwigTest;

/**
 * Class TwigExtension
 *
 * @package benf\neo
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class TwigExtension extends AbstractExtension
{
    /**
     * return string
     */
    public function getName()
    {
        return "Neo";
    }

    /**
     * @return array|TwigTest[]
     */
    public function getTests()
    {
        return [
            new TwigTest('neoblock', [$this, 'isNeoBlock']),
        ];
    }

    /**
     * Determines if a value is a Neo block model.
     *
     * @param $value
     * @return bool
     */
    public function isNeoBlock($value)
    {
        return $value instanceof Block;
    }

    // Useless methods below

    /**
     * Added this to avoid PHP error of undeclared method.
     * This method is deprecated so it should eventually be removed when safe to do so.
     *
     * @param Environment $environment
     */
    public function initRuntime(Environment $environment)
    {
    }

    /**
     * Added this to avoid PHP error of undeclared method.
     * This method is deprecated so it should eventually be removed when safe to do so.
     *
     * @return array
     */
    public function getGlobals()
    {
        return [];
    }
}
