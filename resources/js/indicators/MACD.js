import Indicator from "./Indicator";

export default class MACD extends Indicator
{
    prepare(data, length)
    {
        for (let key in data)
        {
            data[key] = this.objectMap((val, k) =>
            {
                return {time: k / 1000, value: val};
            }, data[key]);

            this.fillFrontGaps(length, data[key]);
        }

        let prevHist = 0;
        for (let i in data.divergence)
        {
            var point = data.divergence[i];
            var hist = data.macd[i].value - data.signal[i].value;

            if (point.value > 0)
                if (prevHist > hist) point.color = '#B2DFDB';
                else point.color = '#26a69a';
            else if (prevHist < hist) point.color = '#FFCDD2';
            else point.color = '#EF5350';

            prevHist = hist;
        }

        return data;
    }

    init(data, chart)
    {
        let series = {};

        series.divergence = chart.addHistogramSeries({
            lineWidth: 1,
            // title: 'divergence',
            crosshairMarkerVisible: true,
        });

        series.macd = chart.addLineSeries({
            color: '#0094ff',
            lineWidth: 1,
            // title: 'macd',
            crosshairMarkerVisible: true
        });

        series.signal = chart.addLineSeries({
            color: '#ff6a00',
            lineWidth: 1,
            // title: 'signal',
            crosshairMarkerVisible: true,
        });

        return series;
    }

    update(series, data)
    {
        for (let i in series)
            series[i].setData(data[i]);
    }

    setMarkers(series, markers)
    {
        series.MACD.macd.setMarkers(markers);
    }
}