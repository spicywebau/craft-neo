<?php

namespace benf\neo\enums;

/**
 * Pseudo-enum for representing a block type group's dropdown behaviour.
 *
 * @package benf\neo\enums
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.1.0
 */
abstract class BlockTypeGroupDropdown
{
    public const Show = 'show';
    public const Hide = 'hide';
    public const Global = 'global';
}
