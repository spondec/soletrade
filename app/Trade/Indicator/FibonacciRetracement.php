<?php

namespace App\Trade\Indicator;

class FibonacciRetracement
{

    // This source code is subject to the terms of the Mozilla Public License 2.0 at https://mozilla.org/MPL/2.0/
// © ceyhun

//@version=4

//Upward Retracement
//Downward Retracement
//http://oahelp.dynamictrend.com/Fibonacci_Retracement.htm

study(title="Auto Fibonacci", shorttitle="AutoFibo", overlay=true)
FPeriod = input(144, title="Fibo Period")
plotF1618 = input(title="Plot 1.618 Level?", type=input.bool, defval=false)

Fhigh = highest(FPeriod)
Flow = lowest(FPeriod)
FH = highestbars(high, FPeriod)
FL = lowestbars(low, FPeriod)
downfibo = FH < FL

F0 = downfibo ? Flow : Fhigh
F236 = downfibo ? (Fhigh - Flow) * 0.236 + Flow : Fhigh - (Fhigh - Flow) * 0.236
F382 = downfibo ? (Fhigh - Flow) * 0.382 + Flow : Fhigh - (Fhigh - Flow) * 0.382
F500 = downfibo ? (Fhigh - Flow) * 0.500 + Flow : Fhigh - (Fhigh - Flow) * 0.500
F618 = downfibo ? (Fhigh - Flow) * 0.618 + Flow : Fhigh - (Fhigh - Flow) * 0.618
F786 = downfibo ? (Fhigh - Flow) * 0.786 + Flow : Fhigh - (Fhigh - Flow) * 0.786
F1000 = downfibo ? (Fhigh - Flow) * 1.000 + Flow : Fhigh - (Fhigh - Flow) * 1.000
F1618 = downfibo ? (Fhigh - Flow) * 1.618 + Flow : Fhigh - (Fhigh - Flow) * 1.618

Fcolor = downfibo ? #00cc00 : #E41019
Foffset = downfibo ? FH : FL

plot(F0, color=Fcolor, linewidth=2, trackprice=true, show_last=1, title='0', transp=0)
plot(F236, color=Fcolor, linewidth=1, trackprice=true, show_last=1, title='0.236', transp=0)
plot(F382, color=Fcolor, linewidth=1, trackprice=true, show_last=1, title='0.382', transp=0)
plot(F500, color=Fcolor, linewidth=2, trackprice=true, show_last=1, title='0.5', transp=0)
plot(F618, color=Fcolor, linewidth=1, trackprice=true, show_last=1, title='0.618', transp=0)
plot(F786, color=Fcolor, linewidth=1, trackprice=true, show_last=1, title='0.786', transp=0)
plot(F1000, color=Fcolor, linewidth=2, trackprice=true, show_last=1, title='1', transp=0)
plot(plotF1618 and F1618 ? F1618 : na, color=Fcolor, linewidth=3, trackprice=true, show_last=1, title='1.618', transp=0)

plotshape(F0, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%0", offset=15, transp=30)
plotshape(F236, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%23.6", offset=15, transp=30)
plotshape(F382, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%38.2", offset=15, transp=30)
plotshape(F500, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%50", offset=15, transp=30)
plotshape(F618, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%61.8", offset=15, transp=30)
plotshape(F786, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%78.6", offset=15, transp=30)
plotshape(F1000, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%100", offset=15, transp=30)
plotshape(plotF1618 and F1618 ? F1618 : na, style=shape.labeldown, location=location.absolute, color=Fcolor, textcolor=color.black, show_last=1, text="%161.8", offset=15, transp=30)

plotshape(Flow, style=shape.labelup, location=location.absolute, size=size.large, color=color.yellow, textcolor=color.black, show_last=1, text="Low", offset=FL, transp=0)
plotshape(Fhigh, style=shape.labeldown, location=location.absolute, size=size.large, color=color.yellow, textcolor=color.black, show_last=1, text="High", offset=FH, transp=0)

//alertcondition(FH > FL and crossover(close, F236), title="Upward Buy F236 Signal", message="Upward Buy F236")
//alertcondition(FH > FL and crossover(close, F382), title="Upward Buy F382 Signal", message="Upward Buy F382")
//alertcondition(FH > FL and crossover(close, F500), title="Upward Buy F500 Signal", message="Upward Buy F500")
//alertcondition(FH > FL and crossover(close, F618), title="Upward Buy F618 Signal", message="Upward Buy F618")
//alertcondition(FH > FL and crossover(close, F786), title="Upward Buy F786 Signal", message="Upward Buy F786")
//
//alertcondition(FH > FL and crossunder(close, F236), title="Upward Sell F236 Signal", message="Upward Sell F236")
//alertcondition(FH > FL and crossunder(close, F382), title="Upward Sell F382 Signal", message="Upward Sell F382")
//alertcondition(FH > FL and crossunder(close, F500), title="Upward Sell F500 Signal", message="Upward Sell F500")
//alertcondition(FH > FL and crossunder(close, F618), title="Upward Sell F618 Signal", message="Upward Sell F618")
//alertcondition(FH > FL and crossunder(close, F786), title="Upward Sell F786 Signal", message="Upward Sell F786")
//
//alertcondition(FH < FL and crossover(close, F236), title="Downward Buy F236 Signal", message="Downward Buy F236")
//alertcondition(FH < FL and crossover(close, F382), title="Downward Buy F382 Signal", message="Downward Buy F382")
//alertcondition(FH < FL and crossover(close, F500), title="Downward Buy F500 Signal", message="Downward Buy F500")
//alertcondition(FH < FL and crossover(close, F618), title="Downward Buy F618 Signal", message="Downward Buy F618")
//alertcondition(FH < FL and crossover(close, F786), title="Downward Buy F786 Signal", message="Downward Buy F786")
//
//alertcondition(FH < FL and crossunder(close, F236), title="Downward Sell F236 Signal", message="Downward Sell F236")
//alertcondition(FH < FL and crossunder(close, F382), title="Downward Sell F382 Signal", message="Downward Sell F382")
//alertcondition(FH < FL and crossunder(close, F500), title="Downward Sell F500 Signal", message="Downward Sell F500")
//alertcondition(FH < FL and crossunder(close, F618), title="Downward Sell F618 Signal", message="Downward Sell F618")
//alertcondition(FH < FL and crossunder(close, F786), title="Downward Sell F786 Signal", message="Downward Sell F786")




}