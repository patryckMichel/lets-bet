@extends('layouts.admin')

@section('title', 'Jogos')
@section('heading', 'Jogos')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Jogos</h2>
  <p class="text-body-tertiary mb-0">Ative, coloque em manutenção ou marque como em breve.</p>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm fs-9 mb-0">
        <thead>
          <tr class="bg-body-secondary">
            <th class="ps-3 border-top border-translucent">Jogo</th>
            <th class="border-top border-translucent">Slug</th>
            <th class="border-top border-translucent">Status</th>
            <th class="pe-3 border-top border-translucent">Ação</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($games as $game)
            <tr>
              <td class="align-middle ps-3 fw-semibold">{{ $game->name }}</td>
              <td class="align-middle">{{ $game->slug }}</td>
              <td class="align-middle"><span class="admin-badge">{{ $game->status }}</span></td>
              <td class="align-middle pe-3">
                <form method="POST" action="{{ route('admin.games.status', $game) }}" class="d-flex gap-2 align-items-center">
                  @csrf
                  @method('PATCH')
                  <select name="status" class="form-select form-select-sm" style="width:auto">
                    <option value="active" @selected($game->status === 'active')>active</option>
                    <option value="maintenance" @selected($game->status === 'maintenance')>maintenance</option>
                    <option value="coming_soon" @selected($game->status === 'coming_soon')>coming_soon</option>
                  </select>
                  <button class="btn btn-sm btn-primary" type="submit">Salvar</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
