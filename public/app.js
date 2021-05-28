window.addEventListener('load', function ()
{
    document.body.style.backgroundColor = "black";

    var charts = [];
    var series = [];

    function newChart(name, width, height)
    {
        charts[name] = LightweightCharts.createChart(document.body, {
            width: width,
            height: height,
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

        charts[name].subscribeCrosshairMove(function (param)
        {
            var position = charts[name].timeScale().scrollPosition();
            var visibleRange = charts[name].timeScale().getVisibleRange();
            var visibleLocalRange = charts[name].timeScale().getVisibleLogicalRange();


            for (var key in charts)
            {
                timeScale = charts[key].timeScale();
                console.log(charts[name].options())

                if (key !== name && timeScale.position !== position)
                {
                    // timeScale.scrollToPosition(position, false);
                    // timeScale.setVisibleRange(visibleRange);
                    console.log(visibleLocalRange)
                    timeScale.setVisibleLogicalRange(visibleLocalRange);
                }
            }
        });

        return charts[name];
    }

    if (chartData.rsi)
    {
        series['rsi'] = newChart('rsi', 1280, 200).addLineSeries({
            color: 'purple',
            lineWidth: 2,
            title: 'rsi',
            crosshairMarkerVisible: true,
            priceScaleId: 'left'
        });

        series['rsi'].setData(chartData.rsi);
    }

    if (chartData.macd)
    {
        series['macd'] = newChart('macd', 1280, 200);

        let len = chartData.macd[2].length;
        var prevHist = 0;

        for (i = 0; i < len; i++)
        {
            var point = chartData.macd[2][i];
            var hist = chartData.macd[0][i].value - chartData.macd[1][i].value;

            if (point.value > 0)
            {
                if (prevHist > hist) point.color = '#B2DFDB'; else point.color = '#26a69a';
            } else
            {
                if (prevHist < hist) point.color = '#FFCDD2'; else point.color = '#EF5350';
            }

            prevHist = hist;
        }

        series['macd'].addLineSeries({
            color: '#0094ff',
            lineWidth: 2,
            // title: 'macd',
            crosshairMarkerVisible: true,
        }).setData(chartData.macd[0]);

        series['macd'].addLineSeries({
            color: '#ff6a00',
            lineWidth: 2,
            // title: 'signal',
            crosshairMarkerVisible: true,
        }).setData(chartData.macd[1]);

        series['macd'].addHistogramSeries({
            lineWidth: 2,
            // title: 'divergence',
            crosshairMarkerVisible: true,
        }).setData(chartData.macd[2]);
    }

    series['candles'] = newChart('candles', 1280, 720).addCandlestickSeries({priceScaleId: 'right'});
    series['candles'].setData(chartData.candles);
    series['candles'].setMarkers(chartData.markers);

    series['rsi'].createPriceLine({
        price: 70.0,
        color: '#1477a5',
        lineWidth: 1,
        lineStyle: LightweightCharts.LineStyle.Solid,
        axisLabelVisible: true,
    });

    series['rsi'].createPriceLine({
        price: 30.0,
        color: '#1477a5',
        lineWidth: 1,
        lineStyle: LightweightCharts.LineStyle.Solid,
        axisLabelVisible: true,
    });

    window.series = series;
    window.charts = charts;
});
