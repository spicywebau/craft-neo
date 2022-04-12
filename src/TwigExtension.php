<?php

namespace benf\neo;

use benf\neo\elements\Block;
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
}
