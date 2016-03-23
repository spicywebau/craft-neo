import Twig from 'twig'
import Craft from 'craft'

Twig.extendFilter('t', function(label, placeholders)
{
	return Craft.t(label, placeholders)
})

let id = 0
Twig.extendFunction('uniqueId', function()
{
	return 'uid' + (id++)
})
