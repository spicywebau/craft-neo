<?php

namespace benf\neo\helpers;

/**
 * Class Memoize
 *
 * @package benf\neo\helpers
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Memoize
{
    public static array $blockTypeRecordsById = [];
    public static array $blockTypesById = [];
    public static array $blockTypesByHandle = [];
    public static array $blockTypesByFieldId = [];
    public static array $blockTypeGroupsById = [];
    public static array $blockTypeGroupsByFieldId = [];
}
