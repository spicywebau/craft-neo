<?php
namespace Craft;

/**
 * The Neo_ElementType class is an abstract class that defines all of the element types that are available in Craft.
 *
 * This class is a poor man's version of an enum, since PHP does not have support for native enumerations.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://craftcms.com/license Craft License Agreement
 * @see       http://craftcms.com
 * @package   craft.app.enums
 * @since     1.0
 */
abstract class Neo_ElementType extends BaseEnum
{
	// Constants
	// =========================================================================

	const NeoBlock = 'Neo_Block';
}
