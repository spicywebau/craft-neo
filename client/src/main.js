import Configurator from './configurator/Configurator'
import Input from './input/Input'

const context = typeof window !== 'undefined' ? window : this

const configurators = []
const inputs = []

context.Neo = {
	Configurator,
	Input,

	configurators,
	inputs,

	createConfigurator(settings = {})
	{
		const configurator = new Configurator(settings)
		configurators.push(configurator)

		return configurator
	},

	createInput(settings = {})
	{
		const input = new Input(settings)
		inputs.push(input)

		return input
	},
}
