@extends('layouts.admin')

@section('title', 'Logs')
@section('heading', 'Logs')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Logs administrativos</h2>
  <p class="text-body-tertiary mb-0">Auditoria de ações feitas no painel (bloqueios, configs, comissões, etc.).</p>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm fs-9 mb-0">
        <thead>
          <tr class="bg-body-secondary">
            <th class="ps-3 border-top border-translucent">Quando</th>
            <th class="border-top border-translucent">Admin</th>
            <th class="border-top border-translucent">Ação</th>
            <th class="border-top border-translucent">Alvo</th>
            <th class="border-top border-translucent">IP</th>
            <th class="pe-3 border-top border-translucent">Detalhes</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($logs as $log)
            <tr>
              <td class="align-middle ps-3 text-nowrap">
                {{ $log->created_at?->format('d/m/Y H:i:s') }}
              </td>
              <td class="align-middle">
                {{ $log->admin?->email ?? ('#'.$log->admin_id) }}
              </td>
              <td class="align-middle"><code>{{ $log->action }}</code></td>
              <td class="align-middle">
                @if ($log->subject_type)
                  <span class="small text-body-tertiary">{{ class_basename($log->subject_type) }}</span>
                  #{{ $log->subject_id }}
                @else
                  —
                @endif
              </td>
              <td class="align-middle">{{ $log->ip ?: '—' }}</td>
              <td class="align-middle pe-3" style="max-width:280px;">
                @if ($log->after || $log->before)
                  <details>
                    <summary class="small">ver</summary>
                    <pre class="small mb-0 mt-1" style="white-space:pre-wrap;max-height:160px;overflow:auto;">@json(['before' => $log->before, 'after' => $log->after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)</pre>
                  </details>
                @else
                  —
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="ps-3 py-4 text-body-tertiary">Nenhum log registrado ainda.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if ($logs->hasPages())
    <div class="card-footer">{{ $logs->links() }}</div>
  @endif
</div>
@endsection
