<?php
// Pluggable datasource for PHP Weathermap 0.9
// - return a static value

// TARGET static:10M
// TARGET static:2M:256K

class WeatherMapDataSource_prometheus extends WeatherMapDataSource
{
    function Recognise($targetString)
    {
        if (preg_match("/^prometheus:.*$/", $targetString)) {
            return true;
        } else {
            return false;
        }
    }

    function ReadData($targetString, &$map, &$item)
    {
        $parts = explode(':', $targetString);
        $router = $parts[1];
        $device = $parts[2];

        $queryRx = "irate(node_network_receive_bytes{instance='$router:9100',device='$device'}[5m])";
        $queryTx = "irate(node_network_transmit_bytes{instance='$router:9100',device='$device'}[5m])";

        $rxRate = $this->prometheusQuery($queryRx) * 8;
        $txRate = $this->prometheusQuery($queryTx) * 8;

        return (array($rxRate, $txRate, 0));
    }

    function prometheusQuery($query)
    {
        $time = time();
        $query = urlencode($query);
        $url = "http://localhost:9090/api/v1/query?time=$time&query=$query";
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
