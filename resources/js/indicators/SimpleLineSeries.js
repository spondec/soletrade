import Indicator from "./Indicator";

export default class SimpleLineSeries extends Indicator
{
    constructor(newChart = true, seriesOptions = {})
    {
        super(newChart);
        this.seriesOptions = seriesOptions;
    }

    prepare(data, length)
    {
        data = this.objectMap((val, key) =>
        {
            return {time: key / 1000, value: val}
        }, data);

        this.fillFrontGaps(length, data);

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