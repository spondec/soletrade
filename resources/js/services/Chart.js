import {createChart, CrosshairMode} from 'lightweight-charts';

export default class Chart
{
    constructor(container, name = '', options = {})
    {
        if (!container) throw Error('Chart container was not found.');

        options.height = container.offsetHeight;
        options.width = container.offsetWidth;

        this.name = name;
        this.series = [];
        this.options = {...this.defaultOptions(), ...options};
        this.container = container;

        this.chart = createChart(container, this.options);
    }

    timeScale()
    {
        return this.chart.timeScale();
    }

    defaultOptions()
    {
        return {
            width: 400,
            height: 600,

            watermark: {
                color: 'rgb(120,120,120)',
                visible: true,
                text: this.name,
                fontSize: 16,
                horzAlign: 'left',
                vertAlign: 'bottom',
            },

            rightPriceScale: {
                visible: true,
                borderColor: 'rgba(197, 203, 206, 1)',
                // precision: 10,
                width: 60
            },
            leftPriceScale: {},
            layout: {
                backgroundColor: 'rgb(0,0,0)',
                textColor: 'white',
            },
            grid: {
                horzLines: {
                    color: 'rgb(59,59,59)',
                },
                vertLines: {
                    color: 'rgb(59,59,59)',
                },
            },
            crosshair: {
                mode: CrosshairMode.Normal
            },
            timeScale: {
                borderColor: 'rgba(197, 203, 206, 1)',
                timeVisible: true,
            },
            handleScroll: {
                vertTouchDrag: false,
            },
        }
    }

    addHistogramSeries(options = {})
    {
        const series = this.chart.addHistogramSeries(options);
        this.series.push(series);

        return series;
    }

    addLineSeries(options = {})
    {
        const series = this.chart.addLineSeries(options);
        this.series.push(series);

        return series;
    }

    addCandlestickSeries(options = {})
    {
        const series = this.chart.addCandlestickSeries(options);
        this.series.push(series);

        return series;
    }

    resize(width, height)
    {
        this.chart.resize(width, height, false);
    }

    showRange(a, b)
    {
        this.chart.timeScale().setVisibleRange({from: a, to: b});
    }

    remove()
    {
        this.chart.remove();
        this.chart = null;
        this.series = null;
    }
}
