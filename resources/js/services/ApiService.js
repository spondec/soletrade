import axios from "axios";

export default class ApiService
{
    static async get(url, config)
    {
        return this.handle(await axios.get(url, config));
    }

    static setErrorHandler(callback)
    {
        axios.interceptors.response.use(response =>
        {
            return response;
        }, error =>
        {
            callback(error);
        });
    }

    static handle(response)
    {
        if (response.status === 200)
        {
            // console.log(response)
            return response.data;
        }

        return [];
    }

    static async recentTrades()
    {
        return await this.get("api/trades/recent");
    }

    static async exchanges()
    {
        return await this.get('api/exchanges');
    }

    static async strategies()
    {
        return await this.get('api/strategies');
    }

    static async balances()
    {
        return await this.get('api/exchanges/balances');
    }

    static async chartData()
    {
        return await this.get('api/chart');
    }

    static async candles(exchange, symbol, interval, indicators, indicatorConfig, limit, strategy = null, range = {})
    {
        return await this.get('api/chart', {
            params: {
                strategy: strategy,
                symbol: symbol,
                exchange: exchange,
                interval: interval,
                indicators: indicators,
                indicatorConfig: indicatorConfig,
                limit: limit,
                range: range
            }
        });
    }
}