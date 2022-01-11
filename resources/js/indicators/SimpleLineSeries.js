import Indicator from "./Indicator";

export default class SimpleLineSeries extends Indicator
{
    seriesOptions = {
        priceLineVisible: false,
        pane: this.pane
    }

    constructor(newPane = true, seriesOptions = {})
    {
        super(newPane);
        this.seriesOptions = {...this.seriesOptions, ...seriesOptions};
    }

    prepare(data, length)
    {
        data = this.objectMap((val, key) =>
        {
            return {time: key / 1000, value: val}
        }, data);
        return data;
    }

    init(data, chart)
    {
        return chart.addLineSeries(this.seriesOptions);
    }

    update(series, data)
    {
        series.setData(data);
    }
}