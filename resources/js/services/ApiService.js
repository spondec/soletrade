import axios from "axios";

export default class ApiService
{
    static log = true;

    static get(url, callback)
    {
        axios.get(url).then(response =>
        {
            callback(response.data);

            if (this.log)
                console.log(response);
        }).catch(error =>
        {
            console.log(error);

            if (this.log)
                alert('An API error occured.');
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

    static async candles(exchange, symbol, interval)
    {
        return (await axios.get('api/chart', {
            params: {
                symbol: symbol,
                exchange: exchange,
                interval: interval
            }
        })).data;
    }
}