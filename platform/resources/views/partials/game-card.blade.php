<article class="game-card {{ $game->is_featured ? 'is-featured' : '' }}">
  <div class="game-thumb game-thumb--{{ $game->category }}">
    <span>{{ strtoupper($game->category) }}</span>
  </div>
  <div class="game-body">
    <h3>{{ $game->name }}</h3>
    <p>{{ $game->short_description }}</p>
    @if ($game->isPlayable())
      <a class="btn btn-primary btn-sm" href="{{ route('games.show', $game->slug) }}">
        {{ $game->statusLabel() }}
      </a>
    @else
      <span class="badge">{{ $game->statusLabel() }}</span>
    @endif
  </div>
</article>
