window.addEventListener('load', function ()
{
    var chart = LightweightCharts.createChart(document.body, {
        width: 2560,
        height: 1000,
        rightPriceScale: {
            visible: true,
            borderColor: 'rgba(197, 203, 206, 1)',
        },
        leftPriceScale: {
            visible: true,
            borderColor: 'rgba(197, 203, 206, 1)',
        },
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
            mode: LightweightCharts.CrosshairMode.Normal,
        },
        timeScale: {
            borderColor: 'rgba(197, 203, 206, 1)',
        },
        handleScroll: {
            vertTouchDrag: false,
        },
    });

    if (chartData.rsi)
    {
        const rsi = chart.addLineSeries({
            color: 'purple',
            lineWidth: 2,
            title: 'rsi',
            crosshairMarkerVisible: true,
            priceScaleId: 'left'
        });

        rsi.setData(chartData.rsi);
    }

    if (chartData.macd)
    {
        const macd = chart.addLineSeries({
            color: '#0094ff',
            lineWidth: 2,
            title: 'macd',
            crosshairMarkerVisible: true,
            priceScaleId: 'left'
        });

        macd.setData(chartData.macd[0]);

        const signal = chart.addLineSeries({
            color: '#ff6a00',
            lineWidth: 2,
            title: 'signal',
            crosshairMarkerVisible: true,
            priceScaleId: 'left'
        });

        signal.setData(chartData.macd[1]);

        let len = chartData.macd[2].length;
        var prevHist = 0;

        for (var i = 0; i < len; i++)
        {
            var point = chartData.macd[2][i];
            var hist = chartData.macd[0][i].value - chartData.macd[1][i].value;

            if (point.value > 0)
            {
                if (prevHist > hist)
                {
                    point.color = '#B2DFDB';

                } else
                {
                    point.color = '#26a69a';
                }
            } else
            {
                if (prevHist < hist)
                {
                    point.color = '#FFCDD2';

                } else
                {
                    point.color = '#EF5350';
                }
            }

            prevHist = hist;
        }

        const divergence = chart.addHistogramSeries({
            lineWidth: 2,
            title: 'signal',
            crosshairMarkerVisible: true,
            priceScaleId: 'left'
        });

        divergence.setData(chartData.macd[2]);
    }

    const candlestickSeries = chart.addCandlestickSeries({priceScaleId: 'right'});
    candlestickSeries.setData(chartData.candles);
    candlestickSeries.setMarkers(chartData.markers);

    console.log(chartData);
});
