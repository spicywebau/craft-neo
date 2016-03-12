import Twig from 'twig'
import Craft from 'craft'

Twig.extendFilter('t', function(label, placeholders)
{
	return Craft.t(label, placeholders)
})
