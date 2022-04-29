export default class IndicatorManager
{
    constructor(handlers)
    {
        if (!handlers)
        {
            throw Error('handlers is undefined.');
        }

        this.handlers = handlers;
    }

    handler(name)
    {
        const handler = this.handlers[name];
        if (handler === undefined)
        {
            throw Error('No handler found for ' + name);
        }

        if (handler instanceof Function)
        {
            this.handlers[name] = handler();
        }

        return this.handlers[name];
    }
}