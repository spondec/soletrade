import axios from "axios";

export default class ApiService
{
    static log = true;

    static get(url, callback)
    {
        axios.get(url).then(response =>
        {
            callback(response.data);
        }).catch(error =>
        {
            console.log(error);
        });
    }

    static async exchanges()
    {
        return (await axios.get('api/exchanges')).data;
    }

    static async balances()
    {
        return (await axios.get('api/exchanges/balances')).data;
    }

    static async trades()
    {
        return (await axios.get('api/trades')).data;
    }

    static async chartData()
    {
        return (await axios.get('api/chart')).data;
    }

    static async candles(exchange, symbol, interval, indicators, limit)
    {
        return (await axios.get('api/chart', {
            params: {
                symbol: symbol,
                exchange: exchange,
                interval: interval,
                indicators: indicators,
                limit: limit
            }
        })).data;
    }
}