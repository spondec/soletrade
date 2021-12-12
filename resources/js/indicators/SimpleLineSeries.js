import Indicator from "./Indicator";

export default class SimpleLineSeries extends Indicator
{
    constructor(newPane = true, seriesOptions = {})
    {
        super(newPane);
        this.seriesOptions = seriesOptions;
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
        return chart.addLineSeries({...this.seriesOptions, ...{pane: this.pane}});
    }

    update(series, data)
    {
        series.setData(data);
    }
}