<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'slug' => 'tigre-aviator',
                'name' => 'Tigre Aviator',
                'short_description' => 'Crash game com multiplicador em tempo real.',
                'category' => 'crash',
                'thumbnail' => 'images/games/tigre-aviator-logo.png',
                'launch_url' => null,
                'status' => Game::STATUS_ACTIVE,
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'tigre-truco',
                'name' => 'Tigre do Truco',
                'short_description' => 'Truco 1x1 ou 2x2 — apostas rápidas até 12 pontos.',
                'category' => 'cards',
                'thumbnail' => 'images/games/tigre-truco.png',
                'launch_url' => null,
                'status' => Game::STATUS_ACTIVE,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'fortune-tiger',
                'name' => 'Fortune Tiger',
                'short_description' => 'Slots clássico do tigre da sorte.',
                'category' => 'slots',
                'thumbnail' => null,
                'launch_url' => null,
                'status' => Game::STATUS_COMING_SOON,
                'is_featured' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'crash-royale',
                'name' => 'Crash Royale',
                'short_description' => 'Novo crash com rodadas rápidas.',
                'category' => 'crash',
                'thumbnail' => null,
                'launch_url' => null,
                'status' => Game::STATUS_COMING_SOON,
                'is_featured' => false,
                'sort_order' => 4,
            ],
            [
                'slug' => 'roleta-vip',
                'name' => 'Roleta VIP',
                'short_description' => 'Roleta ao vivo para a lista VIP.',
                'category' => 'table',
                'thumbnail' => null,
                'launch_url' => null,
                'status' => Game::STATUS_COMING_SOON,
                'is_featured' => false,
                'sort_order' => 5,
            ],
        ];

        foreach ($games as $game) {
            Game::query()->updateOrCreate(
                ['slug' => $game['slug']],
                $game
            );
        }
    }
}
