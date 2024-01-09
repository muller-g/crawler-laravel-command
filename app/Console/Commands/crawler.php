<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class crawler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crawler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    private $visitedUrls = [];
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = 'https://amleiloeiro.com.br';
        $client = new Client();
        $response = $client->request('GET', $url);
        
        if ($response->getStatusCode() == 200) {
            $html = $response->getBody()->getContents();

            $doc = new \DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new \DOMXPath($doc);

            $lotLinks = $xpath->query('//a[contains(@class, "block relative")]');         

            foreach ($lotLinks as $lotLink) {
                $lotUrl = $lotLink->getAttribute('href');
                if (strpos($lotUrl, 'http') !== 0) {
                    $lotUrl = $url . '/' . ltrim($lotUrl, '/');
                }

                if (in_array($lotUrl, $this->visitedUrls)) {
                    $this->info("Lote já visitado: $lotUrl. Pulando...");
                    continue;
                }  

                $lotData = $this->getDataFromLot($lotUrl);
                $this->visitedUrls[] = $lotUrl;
                $this->saveToCSV($lotUrl, $lotData);
            }

            $this->info('Crawler concluído.');
        } else {
            $this->error('Falha ao acessar a página.');
        }
    }

    private function getDataFromLot($lotUrl)
    {
        $client = new Client();
        $response = $client->request('GET', $lotUrl);

        if ($response->getStatusCode() == 200) {
            $html = $response->getBody()->getContents();

            $doc = new \DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new \DOMXPath($doc);

            $firstDate = $xpath->query('/html/body/main/div[2]/div/div[4]/div[1]/div[2]/div/div/table/tbody/tr[4]/td/div/div[1]/span[1]');
            $secondDate = $xpath->query('/html/body/main/div[2]/div/div[4]/div[1]/div[2]/div/div/table/tbody/tr[4]/td/div/div[1]/span[2]');

            if(gettype($firstDate) === "object" && gettype($secondDate) === "object"){
                $this->lotUnderLot($lotUrl);
            }

            $dates1 = [];
            foreach ($firstDate as $dateNode) {
                $date1 = $dateNode->nodeValue;
                $this->info('Primeira data encontrada: ' . $date1);
                $dates1[] = $date1;
            }

            $dates2 = [];
            foreach ($secondDate as $dateNode) {
                $date2 = $dateNode->nodeValue;
                $this->info('Segunda data encontrada: ' . $date2);
                $dates2[] = $date2;
            }

            $dates = [
                'first_date' => implode(', ', $dates1),
                'second_date' => implode(', ', $dates2),
            ];

            return $dates;
        } else {
            return [];
        }
    }

    public function lotUnderLot($lotUrl)
    {
        $url = $lotUrl;
        $client = new Client();
        $response = $client->request('GET', $url);
        
        if ($response->getStatusCode() == 200) {
            $html = $response->getBody()->getContents();

            $doc = new \DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new \DOMXPath($doc);

            $lotLinks = $xpath->query('//a[contains(@class, "block relative")]');

            foreach ($lotLinks as $lotLink) {
                $lotUrl = $lotLink->getAttribute('href');
                
                if (strpos($lotUrl, 'http') !== 0) {
                    $lotUrl = $url . '/' . ltrim($lotUrl, '/');
                }

                if (in_array($lotUrl, $this->visitedUrls)) {
                    $this->info("Lote já visitado: $lotUrl.");
                    return;
                }  

                $lotData = $this->getDataFromLot($lotUrl);
                $this->visitedUrls[] = $lotUrl;
                $this->saveToCSV($lotUrl, $lotData);
            }
        } else {
            $this->error('Falha ao acessar a página.');
        }
    }

    private function saveToCSV($lotUrl, $lotData)
    {
        $csvData = [
            'lot_url' => $lotUrl,
            'dates' => implode(', ', $lotData),
        ];

        $csvFile = fopen('output.csv', 'a');
        fputcsv($csvFile, $csvData);
        fclose($csvFile);
    }
}
