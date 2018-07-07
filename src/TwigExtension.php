<?php
namespace benf\neo;

use Twig_Extension;
use Twig_Environment;
use Twig_SimpleTest;

use benf\neo\elements\Block;


class TwigExtension extends Twig_Extension
{
	public function getName()
	{
		return "Neo";
	}

	/**
	 * @return array|Twig_SimpleTest[]|\Twig_Test[]
	 */
	public function getTests()
	{
		return [
			new Twig_SimpleTest('neoblock', [$this, 'isNeoBlock']),
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
	 * @param Twig_Environment $environment
	 */
	public function initRuntime(Twig_Environment $environment)
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
