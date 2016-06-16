/**
 * ES2015 Javascript polyfill
 # The Javascript in this plugin is written using the latest features in the language. Not all browsers support the
 # features used, so the following code loads a polyfill provided some random new features exist. This polyfill is
 # hundreds of kilobytes in size, so it's important to only load it if absolutely necessary.
 */

if(!Array.from || !Object.assign || typeof Symbol == 'undefined')
{
	document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/babel-polyfill/6.7.4/polyfill.min.js"><\/script>')
}
