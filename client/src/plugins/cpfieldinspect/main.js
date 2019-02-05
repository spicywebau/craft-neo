import Craft from 'craft'

export function addFieldLinks($element)
{
	if(Craft.CpFieldInspectPlugin)
	{
		Craft.CpFieldInspectPlugin.addFieldLinks()
	}
}
