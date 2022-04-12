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
     * @return TwigTest[]
     */
    public function getTests(): array
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
    public function isNeoBlock($value): bool
    {
        return $value instanceof Block;
    }
}
