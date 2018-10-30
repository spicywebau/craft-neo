import $ from 'jquery'

// @see http://stackoverflow.com/a/12903503/556609
$.fn.insertAt = function(index, $parent)
{
	return this.each(function()
	{
		if(index === 0)
		{
			$parent.prepend(this)
		}
		else
		{
			$parent.children().eq(index - 1).after(this)
		}
	})
}
