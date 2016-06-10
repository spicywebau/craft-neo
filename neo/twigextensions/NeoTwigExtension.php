<?php
namespace Craft;

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

	public function isNeoBlock($value)
	{
		return $value instanceof Neo_BlockModel;
	}
}
