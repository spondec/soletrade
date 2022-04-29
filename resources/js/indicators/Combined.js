import Indicator from "./Indicator";
import IndicatorManager from "./IndicatorManager";

export default class Combined extends Indicator
{
    constructor(newPane = false, handlers)
    {
        super(newPane);

        if (!handlers)
        {
            throw Error("Combined indicator requires a chart.");
        }

        this.manager = new IndicatorManager(handlers);
    }

    getHandler(alias, indicators)
    {
        const configIndicators = this.getConfigIndicators(indicators);

        for (let i in configIndicators)
        {
            if (configIndicators[i]['alias'] === alias)
            {
                return this.manager.handler(configIndicators[i]['name']);
            }
        }

        throw Error("Indicator handler not found.");
    }

    getConfigIndicators(indicators)
    {
        for (let i in indicators)
        {
            if (indicators[i].name === 'Combined')
            {
                return indicators[i].config.indicators;
            }
        }
        return null;
    }

    getAliases(indicators)
    {
        const configIndicators = this.getConfigIndicators(indicators);
        const result = [];

        for (let i in configIndicators)
        {
            result.push(configIndicators[i].alias);
        }

        return result;
    }

    prepare(data, length, indicators)
    {
        const prepared = {};
        const aliases = this.getAliases(indicators);

        for (let i in aliases)
        {
            let alias = aliases[i];
            let column = this.objectColumn(data, alias);
            let handler = this.getHandler(alias, indicators);
            prepared[alias] = handler.prepare(column, Object.keys(column).length, indicators)
        }

        return prepared;
    }

    init(data, chart, indicators)
    {
        const series = {};
        for (let alias in data)
        {
            series[alias] = this.getHandler(alias, indicators).init(data[alias], chart);
        }

        return series;
    }

    update(series, data, indicators)
    {
        for (let alias in series)
        {
            this.getHandler(alias, indicators).update(series[alias], data[alias]);
        }
    }
}