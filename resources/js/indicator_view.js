import SimpleLineSeries from "./indicators/SimpleLineSeries";
import Fib from "./indicators/Fib";
import RSI from "./indicators/RSI";
import MACD from "./indicators/MACD";
import Combined from "./indicators/Combined";

export default {
    ATR: () => new SimpleLineSeries(true, {color: 'rgb(255,0,0)', lineWidth: 1, lineType: 0}),
    MA: () => new SimpleLineSeries(false, {color: 'rgb(0,153,255)', lineWidth: 1, lineType: 0}),
    EMA: () => new SimpleLineSeries(false, {color: 'rgb(49,255,0)', lineWidth: 1, lineType: 0}),
    Fib: () => new Fib(),
    RSI: () => new RSI(true, {color: 'rgb(170,42,252)', lineWidth: 1}),
    MACD: () => new MACD(),
    MFI: () => new SimpleLineSeries(true, {color: 'rgb(128,63,173)', lineWidth: 1}),
    Combined: () =>
    {
        return new Combined(false, this.indicatorHandlers)
    }
}