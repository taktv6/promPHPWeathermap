<?php
// Pluggable datasource for PHP Weathermap 0.9
// - return a static value

// TARGET static:10M
// TARGET static:2M:256K

class WeatherMapDataSource_prometheus extends WeatherMapDataSource
{
    /** @var string */
    protected $prometheusHost = 'http://localhost:9090';

    /**
     * @param string $target
     * @return bool
     */
    function Recognise($target)
    {
        return (bool)preg_match('/^prometheus:.*$/', $target);
    }

    /**
     * @param string $target
     * @param        $map
     * @param        $item
     * @return array
     */
    function ReadData($target, &$map, &$item)
    {
        $parts = explode(':', $target);
        $router = $parts[1];
        $device = $parts[2];

        $queryRx = sprintf(
            'irate(node_network_receive_bytes{instance=\'%s:9100\',device=\'%s\'}[5m])',
            $router,
            $device
        );
        $queryTx = sprintf(
            'irate(node_network_transmit_bytes{instance=\'%s:9100\',device=\'%s\'}[5m])',
            $router,
            $device
        );

        $rxRate = $this->prometheusQuery($queryRx) * 8;
        $txRate = $this->prometheusQuery($queryTx) * 8;

        return (array($rxRate, $txRate, 0));
    }

    /**
     * Query the prometheus Server on localhost
     *
     * @param string $query
     * @return int
     */
    function prometheusQuery($query)
    {
        $time = time();
        $query = urlencode($query);
        $url = sprintf('%s/api/v1/query?time=%s&query=%s', $this->prometheusHost, $time, $query);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        $data = json_decode($content, true);

        if ($data['status'] != "success") {
            return -1;
        }

        if ($data['data']['resultType'] != "vector") {
            return -1;
        }

        return $data['data']['result'][0]['value'][1];
    }
}

// vim:ts=4:sw=4:
