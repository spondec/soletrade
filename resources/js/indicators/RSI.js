import SimpleLineSeries from "./SimpleLineSeries";

export default class RSI extends SimpleLineSeries
{
    init(data, chart)
    {
        const lineSeries = super.init(data, chart);

        lineSeries.createPriceLine({
            price: 70.0,
            color: 'gray',
        });
        lineSeries.createPriceLine({
            price: 30.0,
            color: 'gray',
        });

        return lineSeries;
    }

    setMarkers(series, markers)
    {
        series.RSI.setMarkers(markers);
    }
}