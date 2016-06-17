<?php
namespace Craft;

/**
 * Class NeoTwigExtension
 *
 * @package Craft
 */
class NeoTwigExtension extends \Twig_Extension
{
	public function getName()
	{
		return "Neo";
	}

	public function getTests()
	{
		return [
			'neoblock' => new \Twig_Test_Method($this, 'isNeoBlock'),
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
		return $value instanceof Neo_BlockModel;
	}
}
