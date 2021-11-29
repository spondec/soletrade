import Indicator from "./Indicator";

export default class Fib extends Indicator
{
    constructor(newChart = false)
    {
        super(newChart);
    }

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
        return data;
    }

    init(data, chart)
    {
        const colors = {
            0: '#7c7c7c',
            236: '#e03333',
            382: '#e0d733',
            500: '#148cb2',
            618: '#36fa00',
            702: '#dd00ff',
            786: '#ff4500',
            886: '#00d8ff',
            1000: '#ffffff',
        }
        let series = {};

        for (let i in data)
        {
            series[i] = chart.addLineSeries({
                color: colors[i],
                lastValueVisible: true,
                lineWidth: 1,
                lineType: 1,
                crosshairMarkerVisible: false,
                priceLineVisible: false,
            });
        }

        return series;
    }

    update(series, data)
    {
        for (let i in series)
            series[i].setData(data[i]);
    }
}