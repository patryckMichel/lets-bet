<?php

$raw = json_decode(file_get_contents(__DIR__.'/../public/data/ibge-raw.json'), true);
$out = [];

foreach ($raw as $m) {
    $uf = $m['microrregiao']['mesorregiao']['UF']['sigla']
        ?? ($m['regiao-imediata']['regiao-intermediaria']['UF']['sigla'] ?? null);
    $nome = $m['nome'] ?? null;
    if (! $uf || ! $nome) {
        continue;
    }
    $out[$uf][] = $nome;
}

foreach ($out as $uf => $cities) {
    $cities = array_values(array_unique($cities));
    sort($cities, SORT_STRING | SORT_FLAG_CASE);
    $out[$uf] = $cities;
}

ksort($out);

$path = __DIR__.'/../public/data/br-cities-by-uf.json';
file_put_contents($path, json_encode($out, JSON_UNESCAPED_UNICODE));
echo 'ufs='.count($out).' go='.count($out['GO'] ?? []).' bytes='.filesize($path).PHP_EOL;
@unlink(__DIR__.'/../public/data/ibge-raw.json');
