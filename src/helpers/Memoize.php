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

    /**
     * @since 5.2.12
     */
    public static array $parentFieldInstancesByLayoutElementUuid = [];
}
