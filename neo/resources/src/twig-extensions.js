import Twig from 'twig'
import Craft from 'craft'

import NS from './namespace'

Twig.extendFilter('t', function(label, placeholders)
{
	return Craft.t(label, placeholders)
})

Twig.extendFilter('ns', function(value, type = 'field')
{
	switch(type)
	{
		case 'input':
		case 'field': return NS.fieldName(value)
		case 'id':    return NS.value(value, '-')
		case 'js':    return NS.value(value, '.')
	}

	return NS.value(value, '-')
})

let id = 0
Twig.extendFunction('uniqueId', function()
{
	return 'uid' + (id++)
})
