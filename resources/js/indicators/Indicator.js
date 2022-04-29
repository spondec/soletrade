export default class Indicator
{
    static paneCounter = 0;

    constructor(newPane = true)
    {
        this.pane = newPane ? Indicator.getNewPane() : 0;
    }

    static getNewPane()
    {
        return ++this.paneCounter;
    }

    objectColumn(object, column)
    {
        const result = {};

        for (let i in object)
        {
            let value = object[i][column];
            if (value)
            {
                result[i] = value;
            }
        }

        return result;
    }

    prepare(data, length)
    {
        throw Error('Indicator.prepare() should be overridden.');
    }

    init(data, chart)
    {
        throw Error('Indicator.init() should be overridden.');
    }

    update(series, data)
    {
        throw Error('Indicator.update() should be overridden.');
    }

    setMarkers(series, markers)
    {
        throw Error('Indicator.setMarkers() should be overridden.');
    }

    objectMap(callback, object)
    {
        let o = [];

        for (let key in object)
        {
            o.push(callback(object[key], key));
        }

        return o;
    }

    calcInterval(collection, key)
    {
        let start = 0;
        let interval = 0;

        for (let i in collection)
        {
            if (!start) start = collection[i][key];
            else if (!interval)
            {
                interval = collection[i][key] - start;

                if (!Number.isInteger(interval))
                {
                    throw Error('Index interval must be a integer.');
                }
                break;
            }
        }

        return {start: start, interval: interval};
    }

    fillFrontGaps(length, indicator, value = 0)
    {
        const gap = length - Object.keys(indicator).length;

        if (gap > 0)
        {
            let {start, interval} = this.calcInterval(indicator, 'time');
            let time = start;
            for (let i = 0; i < gap; i++)
            {
                time -= interval;
                indicator.unshift({time: time, value: value});
            }
        }
    }
}
