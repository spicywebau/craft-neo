import Craft from 'craft'

export function addFieldLinks($element)
{
	if(Craft.CpFieldLinksPlugin)
	{
		Craft.CpFieldLinksPlugin.addFieldLinks()
	}
}
