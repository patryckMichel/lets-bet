<?php
$h = '$2y$10$Wa2p5YFPsqf85vrpppIkRuaaC1zraLO71Pr9F3q2D5ZhayKVtkSdm';
$cands = ['password','admin','Admin','Password','123456','12345678','admin123','Admin123','qwerty','secret','goldsvet','betplatform','casino','1234','demo'];
foreach ($cands as $p) {
    echo $p . ': ' . (password_verify($p, $h) ? 'YES' : 'no') . PHP_EOL;
}
echo 'reset_hash=' . password_hash('LestBet369!', PASSWORD_BCRYPT) . PHP_EOL;
