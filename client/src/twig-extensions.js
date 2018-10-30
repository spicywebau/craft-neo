import Twig from 'twig'
import Craft from 'craft'

import NS from './namespace'

const decoderElement = document.createElement('div')
function decodeEntities(str)
{
	decoderElement.innerHTML = str
	return decoderElement.textContent
}

Twig.extendFilter('t', function(label, placeholders)
{
	return Craft.t('neo', decodeEntities(label), placeholders)
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
